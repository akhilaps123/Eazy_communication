<?php

namespace App\Support;

use App\Models\Esim;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 9E: what a CUSTOMER may see of their orders and eSIMs.
 *
 * An eSIM (and its QR code / activation details) is shown to a customer only when ALL hold:
 *   - the eSIM belongs to the signed-in user (esims.user_id) AND its order belongs to them too;
 *   - the order is paid (payment_status = paid, paid_at set) and not cancelled;
 *   - the payment was verified by the SERVER with the gateway (succeeded + verified_at);
 *   - fulfilment is complete (orders.fulfillment_status = fulfilled).
 * Anything else is "not found" (404) — never a placeholder or fake eSIM.
 *
 * Customer-facing stages are derived from the existing status columns only:
 *   awaiting_payment | payment_failed | cancelled | refunded | processing | attention | ready
 */
final class CustomerEsims
{
    public const STAGES = [
        'awaiting_payment' => ['Payment pending', 'warn', 'Payment has not been verified yet. Your eSIM is prepared after payment.'],
        'payment_failed' => ['Payment failed', 'error', 'The payment did not go through. You have not been charged for this order.'],
        'cancelled' => ['Cancelled', 'muted', 'This order was cancelled.'],
        'refunded' => ['Refunded', 'muted', 'This order was refunded.'],
        'processing' => ['Processing', 'info', 'Payment is complete and your eSIM is being prepared. This page updates when it is ready.'],
        'attention' => ['Being reviewed', 'warn', 'Your order needs further processing by our team. Please contact support and quote your order number.'],
        'ready' => ['eSIM ready', 'ok', 'Your eSIM activation details are available.'],
    ];

    /** Orders of this customer (newest first). */
    public static function orders(User $user): Builder
    {
        return Order::query()->where('user_id', $user->getKey());
    }

    /** eSIMs this customer may open (see class doc). */
    public static function esims(User $user): Builder
    {
        $userId = $user->getKey();

        return Esim::query()
            ->where('esims.user_id', $userId)
            ->whereHas('orderItem.order', fn (Builder $o) => self::deliveredScope($o->where('orders.user_id', $userId)));
    }

    /** The eSIM if this customer may see it, otherwise 404. */
    public static function findOrFail(User $user, mixed $id): Esim
    {
        abort_unless(is_numeric($id) && (int) $id > 0, 404);

        return self::esims($user)->whereKey((int) $id)->with('orderItem.order')->firstOrFail();
    }

    /** Paid, server-verified, not cancelled, fulfilled. */
    public static function deliveredScope(Builder $orders): Builder
    {
        return $orders
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->whereNotNull('orders.paid_at')
            ->whereNull('orders.cancelled_at')
            ->where('orders.fulfillment_status', Order::FULFILLMENT_FULFILLED)
            ->whereHas('payments', fn (Builder $p) => $p->where('status', Payment::STATUS_SUCCEEDED)->whereNotNull('verified_at'));
    }

    public static function isDelivered(Order $order): bool
    {
        return self::deliveredScope(Order::query()->whereKey($order->getKey()))->exists();
    }

    /** Customer-facing stage of an order (no provider or internal detail). */
    public static function stage(Order $order, ?bool $delivered = null): string
    {
        return match (true) {
            $order->cancelled_at !== null || $order->payment_status === Order::PAYMENT_CANCELLED => 'cancelled',
            in_array($order->payment_status, [Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED], true) => 'refunded',
            $order->payment_status === Order::PAYMENT_FAILED => 'payment_failed',
            $order->payment_status !== Order::PAYMENT_PAID || $order->paid_at === null => 'awaiting_payment',
            $order->fulfillment_status === Order::FULFILLMENT_NEEDS_ATTENTION => 'attention',
            $order->fulfillment_status === Order::FULFILLMENT_FULFILLED && ($delivered ?? self::isDelivered($order)) => 'ready',
            default => 'processing',
        };
    }

    public static function stageLabel(string $stage): string
    {
        return self::STAGES[$stage][0] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    public static function stageTone(string $stage): string
    {
        return self::STAGES[$stage][1] ?? 'muted';
    }

    public static function stageMessage(string $stage): string
    {
        return self::STAGES[$stage][2] ?? '';
    }

    public static function paymentLabel(Order $order): string
    {
        return match ($order->payment_status) {
            Order::PAYMENT_PENDING => 'Pending payment',
            Order::PAYMENT_PAID => 'Paid',
            Order::PAYMENT_FAILED => 'Payment failed',
            Order::PAYMENT_CANCELLED => 'Cancelled',
            Order::PAYMENT_REFUNDED => 'Refunded',
            Order::PAYMENT_PARTIALLY_REFUNDED => 'Partially refunded',
            default => ucfirst(str_replace('_', ' ', (string) $order->payment_status)),
        };
    }

    /** esims.status → wording that never claims more than we know. */
    public static function activationLabel(?string $status): string
    {
        return match ($status) {
            'assigned' => 'Ready to install',
            'installed' => 'Installed',
            'active' => 'Active',
            'expired' => 'Expired',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    /** 8944 •••• •••• 0777 style (first 4 and last 4 digits). */
    public static function maskIccid(?string $iccid): string
    {
        $iccid = (string) $iccid;

        return strlen($iccid) <= 8 ? $iccid : substr($iccid, 0, 4).' •••• •••• '.substr($iccid, -4);
    }
}
