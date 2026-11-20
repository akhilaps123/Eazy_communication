<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\OrderNumber;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Catalogue\CatalogueFixtures;

/**
 * Test-only Razorpay setup: fake TEST keys, mocked HTTP responses and no real
 * network calls (Http::preventStrayRequests). In-memory database only.
 */
trait PaymentFixtures
{
    use CatalogueFixtures;

    protected const KEY_ID = 'rzp_test_FakeKey12345';

    protected const KEY_SECRET = 'fake_key_secret_for_tests';

    protected const WEBHOOK_SECRET = 'fake_webhook_secret_for_tests';

    protected const RZP_ORDER = 'order_TestOrder0001';

    protected function configureRazorpay(array $overrides = []): void
    {
        config(array_merge([
            'razorpay.mode' => 'test',
            'razorpay.key_id' => self::KEY_ID,
            'razorpay.key_secret' => self::KEY_SECRET,
            'razorpay.webhook_secret' => self::WEBHOOK_SECRET,
            'razorpay.currency' => 'USD',
        ], $overrides));
        Http::preventStrayRequests();
    }

    /** A customer's pending order (as created by the Phase 7 checkout). */
    protected function pendingOrder(User $user, string $total = '19.99', string $currency = 'USD', array $attributes = []): Order
    {
        $plan = $this->sellablePlan(price: $total === '0.00' ? '10.00' : $total, bundle: ['provider_cost' => '0.0100']);
        $order = Order::query()->create($attributes + [
            'order_number' => OrderNumber::candidate(),
            'user_id' => $user->id,
            'currency' => $currency,
            'subtotal' => $total,
            'total' => $total,
            'billing_name' => $user->name,
            'billing_email' => $user->email,
            'billing_country' => 'IN',
            'billing_postal_code' => '560001',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'plan_id' => $plan->id, 'plan_name' => 'Test eSIM · 5 GB - 7 Days', 'bundle_code' => 'SECRET_BUNDLE',
            'unit_price' => $total, 'quantity' => 1, 'line_total' => $total, 'unit_provider_cost' => '0.0100', 'provider_currency' => 'USD',
        ]);

        return $order->fresh();
    }

    protected function gatewayOrder(int $amount = 1999, string $currency = 'USD', string $id = self::RZP_ORDER): array
    {
        return ['id' => $id, 'entity' => 'order', 'amount' => $amount, 'amount_paid' => 0, 'amount_due' => $amount,
            'currency' => $currency, 'receipt' => 'EC-TEST', 'status' => 'created', 'attempts' => 0, 'notes' => [], 'created_at' => 1_763_000_000];
    }

    protected function gatewayPayment(string $status = 'captured', int $amount = 1999, string $currency = 'USD', string $orderId = self::RZP_ORDER, string $id = 'pay_TestPayment001'): array
    {
        return [
            'id' => $id, 'entity' => 'payment', 'amount' => $amount, 'currency' => $currency, 'status' => $status,
            'order_id' => $orderId, 'method' => 'card', 'captured' => $status === 'captured', 'international' => false,
            'card' => ['last4' => '1111', 'network' => 'Visa'],   // must never be stored
            'email' => 'buyer@example.test', 'contact' => '+919999999999',
            'error_code' => $status === 'failed' ? 'BAD_REQUEST_ERROR' : null,
            'error_description' => $status === 'failed' ? 'Payment failed' : null,
            'error_reason' => $status === 'failed' ? 'payment_failed' : null,
            'created_at' => 1_763_000_100,
        ];
    }

    /** Mock Razorpay: create-order and fetch-payment responses. */
    protected function fakeRazorpay(?array $order = null, ?array $payment = null): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response($order ?? $this->gatewayOrder()),
            'api.razorpay.com/v1/payments/*' => Http::response($payment ?? $this->gatewayPayment()),
        ]);
    }

    protected function checkoutSignature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, self::KEY_SECRET);
    }

    /** POST the raw JSON body to the webhook like Razorpay does. */
    protected function sendWebhook(array $payload, ?string $eventId = 'evt_Test0001', ?string $signature = null)
    {
        $raw = json_encode($payload);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== '') {
            $headers['HTTP_X_RAZORPAY_SIGNATURE'] = $signature ?? hash_hmac('sha256', $raw, self::WEBHOOK_SECRET);
        }
        if ($eventId !== null) {
            $headers['HTTP_X_RAZORPAY_EVENT_ID'] = $eventId;
        }

        return $this->call('POST', '/webhooks/razorpay', [], [], [], $headers, $raw);
    }

    protected function webhookPayload(string $event, array $payment, ?array $order = null): array
    {
        $payload = ['payment' => ['entity' => $payment]];
        if ($order) {
            $payload['order'] = ['entity' => $order];
        }

        return ['entity' => 'event', 'account_id' => 'acc_Test', 'event' => $event,
            'contains' => array_keys($payload), 'payload' => $payload, 'created_at' => 1_763_000_200];
    }
}
