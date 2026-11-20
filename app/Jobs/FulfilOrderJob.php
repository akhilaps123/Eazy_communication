<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\EsimGo\FulfilmentService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Fulfils one paid order (Phase 9C). Queued after the payment is verified.
 *
 * - tries = 1: the job itself is never retried by the queue — a purchase must
 *   not be repeated blindly. FulfilmentService is idempotent anyway.
 * - Unique per order while queued, so duplicate dispatches collapse.
 * - When the eSIMs were bought but their details are not ready yet, the job
 *   re-queues itself to FETCH them (read-only), a limited number of times.
 */
class FulfilOrderJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $orderId, public int $check = 1) {}

    public function uniqueId(): string
    {
        return 'fulfil-order-'.$this->orderId;
    }

    public function handle(FulfilmentService $fulfilment): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        $result = $fulfilment->fulfil($order);

        if ($result['result'] === FulfilmentService::RESULT_AWAITING_ESIMS) {
            if ($this->check < (int) config('esimgo.fulfilment.assignment_checks', 5)) {
                self::dispatch($this->orderId, $this->check + 1)
                    ->delay(now()->addSeconds((int) config('esimgo.fulfilment.assignment_retry_seconds', 120)));
            } else {
                $fulfilment->stopWaitingForEsims($order);
            }
        }
    }

    public function failed(?Throwable $e): void
    {
        if ($order = Order::query()->find($this->orderId)) {
            app(FulfilmentService::class)->handleUnexpectedFailure($order, mb_substr((string) $e?->getMessage(), 0, 300));
        }
    }
}
