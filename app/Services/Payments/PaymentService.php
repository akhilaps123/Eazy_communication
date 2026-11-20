<?php

namespace App\Services\Payments;

use App\Jobs\FulfilOrderJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Razorpay payments for existing orders (Phase 8, TEST MODE ONLY).
 *
 * Rules:
 * - Amount and currency always come from the saved order, never the browser.
 * - No currency conversion: the order currency must equal RAZORPAY_CURRENCY.
 * - One Razorpay order per internal order; repeated clicks and retries after a
 *   failure reuse it, so there is never more than one active gateway order.
 * - An order becomes "paid" only after the server has confirmed with Razorpay
 *   that a payment for the stored Razorpay order, amount and currency is
 *   CAPTURED. Authorized-only payments do not mark the order paid.
 * - Every status change runs in a transaction with the payment and order rows
 *   locked, and is idempotent (browser verification and webhooks can both arrive,
 *   more than once and in any order).
 * - No eSIM Go order is created here: on the transition to paid, FulfilOrderJob
 *   is queued (after commit) and FulfilmentService decides (Phase 9C).
 */
class PaymentService
{
    /** Payment rows that may still receive a successful payment. */
    private const OPEN_STATUSES = [Payment::STATUS_CREATED, Payment::STATUS_FAILED, Payment::STATUS_CANCELLED];

    public function __construct(private readonly RazorpayClient $razorpay) {}

    // ------------------------------------------------------------------ state

    /** Can this order be offered online payment right now? (null = yes, else the reason) */
    public function unavailableReason(Order $order): ?string
    {
        if (! $this->razorpay->isConfigured()) {
            return 'Online payment is not available yet.';
        }
        $currency = strtoupper((string) config('razorpay.currency'));
        if ($currency === '' || ! array_key_exists($currency, (array) config('razorpay.currency_exponents'))) {
            return 'Online payment is not available yet.';
        }
        if (strtoupper($order->currency) !== $currency) {
            // Never convert or charge a different amount (Phase 8 currency rule).
            return "Online payment is not available for {$order->currency} orders yet. You have not been charged.";
        }
        if ($order->payment_status !== Order::PAYMENT_PENDING || $order->cancelled_at !== null) {
            return 'This order cannot be paid.';
        }

        return null;
    }

    public function latestPayment(Order $order): ?Payment
    {
        return $order->payments()->where('gateway', Payment::GATEWAY_RAZORPAY)->latest('id')->first();
    }

    // --------------------------------------------------------------- initiate

    /**
     * Prepare Razorpay Checkout for an order owned by $user.
     *
     * @return array<string, mixed> public checkout data only (no secrets)
     */
    public function initiate(User $user, Order $order): array
    {
        $this->assertOwner($user, $order);

        try {
            return Cache::lock('payment-init:'.$order->id, 30)->block(10, function () use ($user, $order) {
                $order->refresh();

                if ($order->payment_status === Order::PAYMENT_PAID) {
                    throw new PaymentException('This order has already been paid.', 409);
                }
                if ($reason = $this->unavailableReason($order)) {
                    throw new PaymentException($reason, 422);
                }
                if ($this->hasSucceededPayment($order)) {
                    throw new PaymentException('This order has already been paid.', 409);
                }

                $currency = strtoupper($order->currency);
                $amountMinor = $this->toMinor((string) $order->total, $currency);
                if ($amountMinor <= 0) {
                    throw new PaymentException('This order has no amount to pay.', 422);
                }

                $existing = $this->latestPayment($order);
                if ($existing?->status === Payment::STATUS_PENDING) {
                    throw new PaymentException('A payment for this order is already being processed. Please wait a few minutes and refresh this page.', 409);
                }

                // Reuse the open Razorpay order (repeated clicks, retry after failure or cancel).
                $payment = ($existing && in_array($existing->status, self::OPEN_STATUSES, true)
                    && $existing->gateway_order_id
                    && bccomp((string) $existing->amount, (string) $order->total, 2) === 0
                    && $existing->currency === $currency)
                    ? $existing
                    : $this->createGatewayOrder($user, $order, $amountMinor, $currency);

                return [
                    'success' => true,
                    'key' => $this->razorpay->keyId(),          // public key id only
                    'order_id' => $payment->gateway_order_id,     // Razorpay order id
                    'amount' => $amountMinor,
                    'currency' => $currency,
                    'name' => (string) config('razorpay.merchant_name'),
                    'description' => 'Order '.$order->order_number,
                    'order_number' => $order->order_number,
                    'prefill' => ['name' => $user->name, 'email' => $user->email],
                ];
            });
        } catch (LockTimeoutException) {
            throw new PaymentException('Your payment is already being prepared. Please try again in a moment.', 409);
        }
    }

    private function createGatewayOrder(User $user, Order $order, int $amountMinor, string $currency): Payment
    {
        // Receipt must be unique per Razorpay order (max 40 characters).
        $attempt = $order->payments()->where('gateway', Payment::GATEWAY_RAZORPAY)->count();
        $receipt = $attempt === 0 ? $order->order_number : $order->order_number.'-'.($attempt + 1);

        $gatewayOrder = $this->razorpay->createOrder($amountMinor, $currency, $receipt, [
            'order_number' => $order->order_number,
        ], $order->id);

        // Never accept a gateway order with a different amount or currency.
        if (! is_string($gatewayOrder['id'] ?? null) || ! str_starts_with($gatewayOrder['id'], 'order_')
            || (int) ($gatewayOrder['amount'] ?? -1) !== $amountMinor
            || strtoupper((string) ($gatewayOrder['currency'] ?? '')) !== $currency) {
            throw new PaymentException('The payment gateway returned an unexpected amount or currency, so payment was not started. You have not been charged.', 502);
        }

        return Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_RAZORPAY,
            'gateway_order_id' => $gatewayOrder['id'],
            'amount' => $order->total,
            'currency' => $currency,
            'status' => Payment::STATUS_CREATED,
            'gateway_response' => ['order' => array_intersect_key($gatewayOrder, array_flip(['id', 'amount', 'currency', 'receipt', 'status', 'created_at']))],
        ]);
    }

    // ----------------------------------------------------------------- verify

    /**
     * After Razorpay Checkout's success handler: check the signature against the
     * STORED Razorpay order id, then confirm the payment with Razorpay itself.
     */
    public function verifyCheckout(User $user, Order $order, string $paymentId, string $gatewayOrderId, string $signature): Payment
    {
        $this->assertOwner($user, $order);

        $payment = $order->payments()
            ->where('gateway', Payment::GATEWAY_RAZORPAY)
            ->where('gateway_order_id', $gatewayOrderId)
            ->first();
        if ($payment === null) {
            throw new PaymentException('This payment does not belong to this order.', 422);
        }
        if (! $this->razorpay->isValidPaymentSignature($payment->gateway_order_id, $paymentId, $signature)) {
            throw new PaymentException('The payment could not be verified (invalid signature). You have not been marked as paid.', 422);
        }

        // A valid signature is not proof of capture: ask Razorpay for the payment status.
        $entity = $this->razorpay->fetchPayment($paymentId, $order->id, $payment->id);

        return $this->applyGatewayPayment($payment, $entity, 'checkout');
    }

    /** Checkout reported a failure: record it only after confirming it with Razorpay. */
    public function recordCheckoutFailure(User $user, Order $order, string $paymentId): Payment
    {
        $this->assertOwner($user, $order);

        $entity = $this->razorpay->fetchPayment($paymentId, $order->id);
        $payment = $order->payments()
            ->where('gateway', Payment::GATEWAY_RAZORPAY)
            ->where('gateway_order_id', (string) ($entity['order_id'] ?? ''))
            ->first();
        if ($payment === null) {
            throw new PaymentException('This payment does not belong to this order.', 422);
        }

        return $this->applyGatewayPayment($payment, $entity, 'checkout_failure');
    }

    // ------------------------------------------------------------------ apply

    /**
     * Apply a Razorpay payment entity (from the API or a signed webhook) to our
     * payment and order. Locked, atomic and idempotent.
     *
     * @throws PaymentException when the entity does not match the payment record
     */
    public function applyGatewayPayment(Payment $payment, array $entity, string $source): Payment
    {
        return DB::transaction(function () use ($payment, $entity, $source) {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            /** @var Order $order */
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            $this->assertMatches($payment, $entity);

            $status = (string) ($entity['status'] ?? '');
            $paymentId = (string) $entity['id'];
            $clean = RazorpayClient::sanitisePayment($entity);
            $response = (array) $payment->gateway_response;

            if ($status === 'captured') {
                if ($payment->status === Payment::STATUS_SUCCEEDED) {
                    if ($payment->gateway_payment_id !== $paymentId) {
                        // A second captured payment for an already-paid order: keep for a manual refund.
                        $response['additional_captured_payments'][$paymentId] = $clean;
                        $payment->gateway_response = $response;
                        $payment->save();
                    }

                    return $payment;   // idempotent
                }

                $response['payment'] = $clean;
                $response['confirmed_by'] = $source;
                $payment->fill([
                    'gateway_payment_id' => $paymentId,
                    'method' => isset($entity['method']) ? mb_substr((string) $entity['method'], 0, 30) : null,
                    'status' => Payment::STATUS_SUCCEEDED,
                    'failure_reason' => null,
                    'paid_at' => now(),
                    'verified_at' => now(),
                    'gateway_response' => $response,
                ])->save();

                if ($order->payment_status !== Order::PAYMENT_PAID) {
                    $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

                    // Phase 9C hand-off: queue fulfilment once, only after this transaction
                    // commits, and only on the transition to paid (repeat webhooks do nothing).
                    // FulfilmentService re-checks everything (verified payment, switches).
                    FulfilOrderJob::dispatch($order->id)->afterCommit();
                }

                return $payment;
            }

            if ($payment->status === Payment::STATUS_SUCCEEDED) {
                return $payment;   // never downgrade a captured payment (out-of-order events)
            }

            if ($status === 'authorized') {
                $response['payment'] = $clean;
                $payment->fill([
                    'gateway_payment_id' => $paymentId,
                    'method' => isset($entity['method']) ? mb_substr((string) $entity['method'], 0, 30) : null,
                    'status' => Payment::STATUS_PENDING,   // authorized, not captured: NOT paid
                    'verified_at' => now(),
                    'gateway_response' => $response,
                ])->save();

                return $payment;
            }

            if ($status === 'failed') {
                if ($payment->status === Payment::STATUS_PENDING && $payment->gateway_payment_id !== $paymentId) {
                    return $payment;   // another attempt is already authorized; keep waiting for it
                }
                $response['failed_attempts'][$paymentId] = $clean;
                $payment->fill([
                    'gateway_payment_id' => $paymentId,
                    'status' => Payment::STATUS_FAILED,
                    'failure_reason' => mb_substr(trim(($entity['error_description'] ?? 'Payment failed').' ('.($entity['error_code'] ?? 'unknown').')'), 0, 500),
                    'gateway_response' => $response,
                ])->save();
                // The order stays pending_payment so the customer can try again.
            }

            return $payment;
        });
    }

    // ---------------------------------------------------------------- helpers

    public function toMinor(string $amount, string $currency): int
    {
        $exponent = (int) (config('razorpay.currency_exponents')[strtoupper($currency)] ?? 2);

        return (int) bcmul($amount, bcpow('10', (string) $exponent), 0);
    }

    private function assertMatches(Payment $payment, array $entity): void
    {
        if (($entity['entity'] ?? 'payment') !== 'payment' || ! is_string($entity['id'] ?? null) || ! str_starts_with($entity['id'], 'pay_')) {
            throw new PaymentException('The payment details from the gateway are not valid.', 422);
        }
        if (($entity['order_id'] ?? null) !== $payment->gateway_order_id) {
            throw new PaymentException('This payment does not belong to this order.', 422);
        }
        if (strtoupper((string) ($entity['currency'] ?? '')) !== strtoupper($payment->currency)) {
            throw new PaymentException('The payment currency does not match the order. You have not been marked as paid.', 422);
        }
        if ((int) ($entity['amount'] ?? -1) !== $this->toMinor((string) $payment->amount, $payment->currency)) {
            throw new PaymentException('The payment amount does not match the order. You have not been marked as paid.', 422);
        }
    }

    private function hasSucceededPayment(Order $order): bool
    {
        return $order->payments()->where('status', Payment::STATUS_SUCCEEDED)->exists();
    }

    private function assertOwner(User $user, Order $order): void
    {
        if ((int) $order->user_id !== (int) $user->id) {
            throw new PaymentException('Order not found.', 404);   // never reveal other customers' orders
        }
    }
}
