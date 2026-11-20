<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\ProviderOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 9F: read-only presentation of an order's lifecycle for the admin
 * "Orders & Fulfillment" pages. It only READS the status columns written by
 * PaymentService (orders.payment_status, payments.status) and
 * FulfilmentService (orders.fulfillment_status, provider_orders.status);
 * it never stores a new status value.
 *
 * Fulfilment "stage" is derived (no new column):
 *   not_started          order not paid, fulfilment not started
 *   queued               order paid, fulfilment not started yet (job queued / waiting for a worker)
 *   processing           orders.fulfillment_status = processing
 *   fulfilled            orders.fulfillment_status = fulfilled
 *   failed               needs_attention + provider order failed (eSIM Go refused; nothing bought)
 *   needs_reconciliation needs_attention + provider order needs_reconciliation (maybe bought)
 *   needs_attention      any other needs_attention (e.g. eSIM details missing, ICCID conflict)
 */
final class OrderLifecycle
{
    /** Filter value => label. Values map onto payments.status (latest attempt). */
    public const PAYMENT_FILTERS = [
        'none' => 'No payment attempt',
        'created' => 'Pending',
        'authorized' => 'Authorized (not captured)',
        'succeeded' => 'Paid / captured',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    /** Filter value => payments.status values. */
    private const PAYMENT_FILTER_STATUSES = [
        'created' => [Payment::STATUS_CREATED],
        'authorized' => [Payment::STATUS_PENDING],     // PaymentService stores "authorized" as pending
        'succeeded' => [Payment::STATUS_SUCCEEDED],
        'failed' => [Payment::STATUS_FAILED],
        'cancelled' => [Payment::STATUS_CANCELLED],
        'refunded' => [Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED],
    ];

    /** orders.payment_status (the order status) => label. */
    public const ORDER_STATUSES = [
        Order::PAYMENT_PENDING => 'Pending payment',
        Order::PAYMENT_PAID => 'Paid',
        Order::PAYMENT_FAILED => 'Payment failed',
        Order::PAYMENT_CANCELLED => 'Cancelled',
        Order::PAYMENT_REFUNDED => 'Refunded',
        Order::PAYMENT_PARTIALLY_REFUNDED => 'Partially refunded',
    ];

    public const FULFILMENT_STAGES = [
        'not_started' => 'Not started',
        'queued' => 'Queued',
        'processing' => 'Processing',
        'fulfilled' => 'Fulfilled',
        'failed' => 'Failed',
        'needs_attention' => 'Needs attention',
        'needs_reconciliation' => 'Needs reconciliation',
    ];

    /** Fulfilment filter options: the stages plus two combinations used by the dashboard. */
    public const FULFILMENT_FILTERS = self::FULFILMENT_STAGES + [
        'awaiting' => 'Awaiting fulfillment (queued or processing)',
        'attention' => 'Needs attention or reconciliation',
    ];

    private const PAYMENT_LABELS = [
        Payment::STATUS_CREATED => 'Pending',
        Payment::STATUS_PENDING => 'Authorized',
        Payment::STATUS_SUCCEEDED => 'Paid / captured',
        Payment::STATUS_FAILED => 'Failed',
        Payment::STATUS_CANCELLED => 'Cancelled',
        Payment::STATUS_REFUNDED => 'Refunded',
        Payment::STATUS_PARTIALLY_REFUNDED => 'Partially refunded',
    ];

    // ------------------------------------------------------------ labels & tones

    public static function paymentLabel(?string $status): string
    {
        return $status === null ? 'No payment' : (self::PAYMENT_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)));
    }

    public static function paymentTone(?string $status): string
    {
        return match ($status) {
            Payment::STATUS_SUCCEEDED => 'ok',
            Payment::STATUS_PENDING, Payment::STATUS_CREATED => 'warn',
            Payment::STATUS_FAILED => 'error',
            Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED => 'info',
            default => '',
        };
    }

    public static function orderLabel(string $status): string
    {
        return self::ORDER_STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function orderTone(string $status): string
    {
        return match ($status) {
            Order::PAYMENT_PAID => 'ok',
            Order::PAYMENT_PENDING => 'warn',
            Order::PAYMENT_FAILED => 'error',
            Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED => 'info',
            default => '',
        };
    }

    /** The derived fulfilment stage of an order (providerOrder should be eager-loaded). */
    public static function stage(Order $order): string
    {
        return match ($order->fulfillment_status) {
            Order::FULFILLMENT_FULFILLED => 'fulfilled',
            Order::FULFILLMENT_PROCESSING => 'processing',
            Order::FULFILLMENT_NEEDS_ATTENTION => match ($order->providerOrder?->status) {
                ProviderOrder::STATUS_FAILED => 'failed',
                ProviderOrder::STATUS_NEEDS_RECONCILIATION => 'needs_reconciliation',
                default => 'needs_attention',
            },
            Order::FULFILLMENT_NOT_STARTED => $order->payment_status === Order::PAYMENT_PAID ? 'queued' : 'not_started',
            default => (string) $order->fulfillment_status,
        };
    }

    public static function stageLabel(string $stage): string
    {
        return self::FULFILMENT_STAGES[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    public static function stageTone(string $stage): string
    {
        return match ($stage) {
            'fulfilled' => 'ok',
            'queued', 'processing' => 'info',
            'failed', 'needs_reconciliation' => 'error',
            'needs_attention' => 'warn',
            default => '',
        };
    }

    // ------------------------------------------------------------ query filters

    /** Search by order number, billing name/email or the account's name/email (SQL LIKE, escaped). */
    public static function search(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes(mb_substr($term, 0, 100), '\\%_').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('order_number', 'like', $like)
            ->orWhere('billing_name', 'like', $like)
            ->orWhere('billing_email', 'like', $like)
            ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
    }

    /** Filter on the status of the order's most recent payment attempt. */
    public static function wherePayment(Builder $query, ?string $filter): Builder
    {
        if ($filter === 'none') {
            return $query->doesntHave('payments');
        }
        $statuses = self::PAYMENT_FILTER_STATUSES[$filter] ?? null;

        return $statuses === null ? $query : $query->whereHas('payments', fn (Builder $p) => $p
            ->whereIn('status', $statuses)
            ->whereRaw('payments.id = (select max(p2.id) from payments as p2 where p2.order_id = payments.order_id)'));
    }

    public static function whereStage(Builder $query, ?string $stage): Builder
    {
        $attention = fn (Builder $q) => $q->where('fulfillment_status', Order::FULFILLMENT_NEEDS_ATTENTION);
        $poStatus = fn (array $statuses) => fn (Builder $po) => $po->whereIn('status', $statuses);
        $unresolved = [ProviderOrder::STATUS_FAILED, ProviderOrder::STATUS_NEEDS_RECONCILIATION];

        return match ($stage) {
            'not_started' => $query->where('fulfillment_status', Order::FULFILLMENT_NOT_STARTED)->where('payment_status', '!=', Order::PAYMENT_PAID),
            'queued' => $query->where('fulfillment_status', Order::FULFILLMENT_NOT_STARTED)->where('payment_status', Order::PAYMENT_PAID),
            'processing' => $query->where('fulfillment_status', Order::FULFILLMENT_PROCESSING),
            'fulfilled' => $query->where('fulfillment_status', Order::FULFILLMENT_FULFILLED),
            'failed' => $attention($query)->whereHas('providerOrder', $poStatus([ProviderOrder::STATUS_FAILED])),
            'needs_reconciliation' => $attention($query)->whereHas('providerOrder', $poStatus([ProviderOrder::STATUS_NEEDS_RECONCILIATION])),
            'needs_attention' => $attention($query)->whereDoesntHave('providerOrder', $poStatus($unresolved)),
            // Combinations (dashboard): queued + processing; needs_attention + needs_reconciliation.
            'awaiting' => $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('fulfillment_status', Order::FULFILLMENT_NOT_STARTED)->where('payment_status', Order::PAYMENT_PAID))
                ->orWhere('fulfillment_status', Order::FULFILLMENT_PROCESSING)),
            'attention' => $attention($query)->whereDoesntHave('providerOrder', $poStatus([ProviderOrder::STATUS_FAILED])),
            default => $query,
        };
    }

    /**
     * Dashboard figures in ONE query, using the same rules as whereStage()
     * (provider_orders.order_id is unique, so the join never duplicates orders).
     *
     * @return array{total: int, pending_payment: int, awaiting: int, fulfilled: int, failed: int, attention: int}
     */
    public static function overview(): array
    {
        $row = Order::query()
            ->leftJoin('provider_orders', 'provider_orders.order_id', '=', 'orders.id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN orders.payment_status = ? THEN 1 ELSE 0 END) AS pending_payment', [Order::PAYMENT_PENDING])
            ->selectRaw('SUM(CASE WHEN (orders.fulfillment_status = ? AND orders.payment_status = ?) OR orders.fulfillment_status = ? THEN 1 ELSE 0 END) AS awaiting',
                [Order::FULFILLMENT_NOT_STARTED, Order::PAYMENT_PAID, Order::FULFILLMENT_PROCESSING])
            ->selectRaw('SUM(CASE WHEN orders.fulfillment_status = ? THEN 1 ELSE 0 END) AS fulfilled', [Order::FULFILLMENT_FULFILLED])
            ->selectRaw('SUM(CASE WHEN orders.fulfillment_status = ? AND provider_orders.status = ? THEN 1 ELSE 0 END) AS failed',
                [Order::FULFILLMENT_NEEDS_ATTENTION, ProviderOrder::STATUS_FAILED])
            ->selectRaw('SUM(CASE WHEN orders.fulfillment_status = ? AND (provider_orders.status IS NULL OR provider_orders.status <> ?) THEN 1 ELSE 0 END) AS attention',
                [Order::FULFILLMENT_NEEDS_ATTENTION, ProviderOrder::STATUS_FAILED])
            ->toBase()
            ->first();

        return array_map(fn ($v) => (int) $v, (array) $row);
    }

    // ------------------------------------------------------------ detail timeline

    /**
     * The lifecycle steps shown on the order page. Each step:
     * ['label', 'state' => done|current|problem|waiting|unavailable, 'detail', 'at' => ?Carbon].
     * Requires payments, providerOrder, items, esims and integrationLogs loaded.
     */
    public static function timeline(Order $order): array
    {
        $payments = $order->payments->sortBy('id');
        $first = $payments->first();
        $latest = $payments->last();
        $verified = $payments->first(fn (Payment $p) => $p->verified_at !== null && $p->status === Payment::STATUS_SUCCEEDED);
        $po = $order->providerOrder;
        $paid = $order->payment_status === Order::PAYMENT_PAID && $order->paid_at !== null;
        $stage = self::stage($order);
        $expected = (int) $order->items->sum('quantity');
        $stored = $order->esims->count();
        $skipped = $order->integrationLogs->where('operation', 'fulfilment_skipped')->sortByDesc('id')->first();

        $steps = [];
        $steps[] = ['label' => 'Order placed', 'state' => 'done', 'detail' => $order->order_number, 'at' => $order->created_at];

        $steps[] = $first
            ? ['label' => 'Payment initiated', 'state' => 'done', 'detail' => ucfirst($first->gateway).' · '.$payments->count().' attempt(s)', 'at' => $first->created_at]
            : ['label' => 'Payment initiated', 'state' => $order->payment_status === Order::PAYMENT_CANCELLED ? 'unavailable' : 'waiting', 'detail' => 'No payment attempt yet', 'at' => null];

        $steps[] = match (true) {
            $verified !== null => ['label' => 'Payment verified', 'state' => 'done', 'detail' => 'Confirmed with the gateway by the server', 'at' => $verified->verified_at],
            $latest?->status === Payment::STATUS_FAILED => ['label' => 'Payment verified', 'state' => 'problem', 'detail' => 'Latest attempt failed'.($latest->failure_reason ? ': '.$latest->failure_reason : ''), 'at' => $latest->updated_at],
            $latest?->status === Payment::STATUS_PENDING => ['label' => 'Payment verified', 'state' => 'current', 'detail' => 'Authorized, waiting for capture', 'at' => $latest->updated_at],
            default => ['label' => 'Payment verified', 'state' => 'waiting', 'detail' => 'Not verified', 'at' => null],
        };

        $steps[] = $paid
            ? ['label' => 'Order marked paid', 'state' => 'done', 'detail' => 'payment_status = paid', 'at' => $order->paid_at]
            : ['label' => 'Order marked paid', 'state' => 'waiting', 'detail' => self::orderLabel($order->payment_status), 'at' => null];

        $steps[] = match (true) {
            ! $paid => ['label' => 'Fulfillment queued', 'state' => 'waiting', 'detail' => 'Queued automatically once the order is paid', 'at' => null],
            $po !== null => ['label' => 'Fulfillment queued', 'state' => 'done', 'detail' => 'Picked up by the fulfillment job', 'at' => $po->created_at],
            $skipped !== null => ['label' => 'Fulfillment queued', 'state' => 'problem', 'detail' => 'Skipped: '.($skipped->error_message ?: ($skipped->request_body['note'] ?? 'see the log below')), 'at' => $skipped->created_at],
            default => ['label' => 'Fulfillment queued', 'state' => 'current', 'detail' => 'Waiting for the queue worker (php artisan queue:work)', 'at' => $order->paid_at],
        };

        $steps[] = match (true) {
            $po === null => ['label' => 'eSIM Go purchase', 'state' => 'waiting', 'detail' => 'Not sent', 'at' => null],
            $po->status === ProviderOrder::STATUS_FAILED => ['label' => 'eSIM Go purchase', 'state' => 'problem', 'detail' => 'Failed — nothing was bought', 'at' => $po->updated_at],
            $po->status === ProviderOrder::STATUS_NEEDS_RECONCILIATION => ['label' => 'eSIM Go purchase', 'state' => 'problem', 'detail' => 'Outcome uncertain — check the eSIM Go account', 'at' => $po->submitted_at ?? $po->updated_at],
            $po->provider_reference !== null => ['label' => 'eSIM Go purchase', 'state' => 'done', 'detail' => 'eSIM Go order '.$po->provider_reference, 'at' => $po->submitted_at],
            default => ['label' => 'eSIM Go purchase', 'state' => 'current', 'detail' => 'Reserved, not yet confirmed', 'at' => $po->created_at],
        };

        $steps[] = match (true) {
            $expected > 0 && $stored >= $expected => ['label' => 'eSIM details retrieved', 'state' => 'done', 'detail' => "{$stored} of {$expected} eSIM(s) stored", 'at' => $order->esims->max('created_at')],
            $po?->provider_reference !== null && $stage === 'needs_attention' => ['label' => 'eSIM details retrieved', 'state' => 'problem', 'detail' => "{$stored} of {$expected} eSIM(s) stored", 'at' => null],
            $po?->provider_reference !== null => ['label' => 'eSIM details retrieved', 'state' => 'current', 'detail' => "{$stored} of {$expected} eSIM(s) stored", 'at' => null],
            default => ['label' => 'eSIM details retrieved', 'state' => 'waiting', 'detail' => "0 of {$expected} eSIM(s)", 'at' => null],
        };

        $steps[] = $stage === 'fulfilled'
            ? ['label' => 'Fulfillment completed', 'state' => 'done', 'detail' => 'Order fulfilled', 'at' => $order->fulfilled_at]
            : ['label' => 'Fulfillment completed', 'state' => in_array($stage, ['failed', 'needs_attention', 'needs_reconciliation'], true) ? 'problem' : 'waiting', 'detail' => self::stageLabel($stage), 'at' => null];

        // Phase 9E: My eSIMs page (immediately on fulfilment) + "eSIM ready" email.
        $email = $order->relationLoaded('esimReadyNotification') ? $order->esimReadyNotification : null;
        $steps[] = match (true) {
            $email?->status === OrderNotification::STATUS_SENT => ['label' => 'Customer receives eSIM details', 'state' => 'done', 'detail' => 'Shown in My eSIMs · email accepted by the mail server', 'at' => $email->sent_at],
            $email?->status === OrderNotification::STATUS_FAILED => ['label' => 'Customer receives eSIM details', 'state' => 'problem', 'detail' => 'Shown in My eSIMs · email failed: '.$email->last_error, 'at' => $email->last_attempt_at],
            $email !== null => ['label' => 'Customer receives eSIM details', 'state' => 'current', 'detail' => 'Shown in My eSIMs · email '.strtolower(OrderNotification::LABELS[$email->status] ?? $email->status), 'at' => $email->queued_at],
            $stage === 'fulfilled' => ['label' => 'Customer receives eSIM details', 'state' => 'current', 'detail' => 'Shown in My eSIMs · email not queued', 'at' => null],
            default => ['label' => 'Customer receives eSIM details', 'state' => 'waiting', 'detail' => 'After fulfilment: My eSIMs page and email', 'at' => null],
        };

        return $steps;
    }
}
