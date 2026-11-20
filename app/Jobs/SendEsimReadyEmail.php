<?php

namespace App\Jobs;

use App\Models\OrderNotification;
use App\Services\Esim\EsimReadyNotifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Phase 9E: sends one "eSIM ready" email. Unique per notification while queued.
 * A failed send is retried by the queue (3 tries); the notification row decides
 * whether anything is sent, so a retry never sends a second copy once it was
 * accepted. This job never touches fulfilment and never buys anything.
 */
class SendEsimReadyEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $uniqueFor = 900;

    public function __construct(public int $notificationId) {}

    public function uniqueId(): string
    {
        return 'esim-ready-email-'.$this->notificationId;
    }

    public function handle(EsimReadyNotifier $notifier): void
    {
        $notifier->deliver($this->notificationId);
    }

    public function failed(?Throwable $e): void
    {
        $row = OrderNotification::query()->find($this->notificationId);
        if ($row !== null && $row->status !== OrderNotification::STATUS_SENT && $row->status !== OrderNotification::STATUS_FAILED) {
            app(EsimReadyNotifier::class)->markFailed($row, 'The email job stopped ('.class_basename($e ?? new \RuntimeException).').');
        }
    }
}
