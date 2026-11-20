<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\EsimGo\FulfilmentService;
use Illuminate\Console\Command;

class EsimGoFulfil extends Command
{
    protected $signature = 'esimgo:fulfil
                            {order? : Order number, e.g. EC-261119-ABC123}
                            {--retry-failed : Try again after a DEFINITE failure (nothing was bought)}
                            {--list : List paid orders and their fulfilment status}';

    protected $description = 'Fulfil (or continue fulfilling) a paid order with eSIM Go — never buys twice';

    public function handle(FulfilmentService $fulfilment): int
    {
        if ($this->option('list') || ! $this->argument('order')) {
            $orders = Order::query()->with('providerOrder')->where('payment_status', Order::PAYMENT_PAID)->latest('id')->limit(50)->get();
            $this->table(['Order', 'Paid at', 'Fulfilment', 'Provider order', 'Reference', 'Last error'], $orders->map(fn (Order $o) => [
                $o->order_number, (string) $o->paid_at, $o->fulfillment_status, $o->providerOrder?->status ?? '—',
                $o->providerOrder?->provider_reference ?? '—', mb_substr((string) $o->providerOrder?->last_error, 0, 80),
            ])->all());

            return self::SUCCESS;
        }

        $order = Order::query()->where('order_number', $this->argument('order'))->first();
        if ($order === null) {
            $this->error('Order not found.');

            return self::FAILURE;
        }

        $result = $fulfilment->fulfil($order, (bool) $this->option('retry-failed'));
        $line = strtoupper($result['result']).': '.$result['message'];
        in_array($result['result'], [FulfilmentService::RESULT_FULFILLED, FulfilmentService::RESULT_AWAITING_ESIMS], true)
            ? $this->info($line) : $this->warn($line);

        return in_array($result['result'], [FulfilmentService::RESULT_FULFILLED, FulfilmentService::RESULT_AWAITING_ESIMS], true)
            ? self::SUCCESS : self::FAILURE;
    }
}
