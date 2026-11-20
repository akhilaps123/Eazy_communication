<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Feature\Database\DatabaseTestCase;

class RazorpayWebhookTest extends DatabaseTestCase
{
    use PaymentFixtures;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $customer = User::factory()->create();
        $this->order = $this->pendingOrder($customer);
        $this->payment = $this->paymentFor($this->order, self::RZP_ORDER);
    }

    /** A payment record as created by "Pay Now" (Razorpay order already created). */
    private function paymentFor(Order $order, string $gatewayOrderId): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay', 'gateway_order_id' => $gatewayOrderId,
            'amount' => $order->total, 'currency' => $order->currency, 'status' => Payment::STATUS_CREATED,
        ]);
    }

    public function test_a_valid_captured_webhook_marks_the_correct_order_paid(): void
    {
        $other = $this->pendingOrder(User::factory()->create());
        $this->paymentFor($other, 'order_OtherOrder0001');

        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment()))
            ->assertOk()->assertJson(['status' => 'processed']);

        $this->assertSame(['paid', 'not_started'], [$this->order->fresh()->payment_status, $this->order->fresh()->fulfillment_status]);
        $this->assertSame(['succeeded', 'pay_TestPayment001'], [$this->payment->fresh()->status, $this->payment->fresh()->gateway_payment_id]);
        $this->assertSame('pending_payment', $other->fresh()->payment_status);   // unrelated order untouched

        $event = WebhookEvent::sole();
        $this->assertSame(['razorpay', 'evt_Test0001', 'payment.captured', true, 'processed', $this->payment->id], [
            $event->gateway, $event->event_id, $event->event_type, $event->signature_valid, $event->processing_status, $event->payment_id,
        ]);
        $stored = json_encode($event->payload);
        $this->assertStringNotContainsString('last4', $stored);   // card details never stored
        $this->assertStringNotContainsString('+919999999999', $stored);
    }

    public function test_order_paid_event_is_supported(): void
    {
        $this->sendWebhook($this->webhookPayload('order.paid', $this->gatewayPayment(), $this->gatewayOrder() + ['status' => 'paid']))
            ->assertOk()->assertJson(['status' => 'processed']);

        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }

    public function test_invalid_or_missing_signatures_are_rejected_and_not_stored(): void
    {
        $payload = $this->webhookPayload('payment.captured', $this->gatewayPayment());

        $this->sendWebhook($payload, signature: hash_hmac('sha256', 'something else', self::WEBHOOK_SECRET))->assertStatus(400);
        $this->sendWebhook($payload, signature: hash_hmac('sha256', json_encode($payload), self::KEY_SECRET))->assertStatus(400);   // key secret is not the webhook secret
        $this->sendWebhook($payload, signature: '')->assertStatus(400);

        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
    }

    public function test_webhooks_are_refused_until_a_webhook_secret_is_configured(): void
    {
        $this->configureRazorpay(['razorpay.webhook_secret' => '']);
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment()), signature: str_repeat('a', 64))->assertStatus(503);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_duplicate_events_are_acknowledged_once(): void
    {
        $payload = $this->webhookPayload('payment.captured', $this->gatewayPayment());

        $this->sendWebhook($payload)->assertJson(['status' => 'processed']);
        $paidAt = $this->order->fresh()->paid_at;
        $this->travel(10)->minutes();
        $this->sendWebhook($payload)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->sendWebhook($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals($paidAt, $this->order->fresh()->paid_at);
    }

    public function test_repeated_events_with_new_ids_do_not_duplicate_records(): void
    {
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment()), 'evt_A');
        $this->sendWebhook($this->webhookPayload('order.paid', $this->gatewayPayment(), $this->gatewayOrder()), 'evt_B');
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment()), 'evt_C');

        $this->assertDatabaseCount('webhook_events', 3);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }

    public function test_failed_webhook_never_marks_paid_and_out_of_order_events_are_safe(): void
    {
        $this->sendWebhook($this->webhookPayload('payment.failed', $this->gatewayPayment('failed', id: 'pay_FailedAttempt1')), 'evt_1')
            ->assertJson(['status' => 'processed']);
        $this->assertSame(['pending_payment', 'failed'], [$this->order->fresh()->payment_status, $this->payment->fresh()->status]);

        // A later attempt is captured (e.g. a retry inside the Checkout window).
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment(id: 'pay_SecondAttempt1')), 'evt_2');
        $this->assertSame(['paid', 'succeeded'], [$this->order->fresh()->payment_status, $this->payment->fresh()->status]);

        // A late "failed" for the first attempt must not undo the captured payment.
        $this->sendWebhook($this->webhookPayload('payment.failed', $this->gatewayPayment('failed', id: 'pay_FailedAttempt1')), 'evt_3');
        $this->assertSame(['paid', 'succeeded', 'pay_SecondAttempt1'], [$this->order->fresh()->payment_status, $this->payment->fresh()->status, $this->payment->fresh()->gateway_payment_id]);
    }

    public function test_authorized_webhook_does_not_mark_paid(): void
    {
        $this->sendWebhook($this->webhookPayload('payment.authorized', $this->gatewayPayment('authorized')))->assertJson(['status' => 'processed']);

        $this->assertSame(['pending_payment', 'pending'], [$this->order->fresh()->payment_status, $this->payment->fresh()->status]);
    }

    public function test_unknown_or_mismatching_payments_cannot_update_any_order(): void
    {
        // A Razorpay order we never created.
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment(orderId: 'order_NotOurs000001')), 'evt_x')
            ->assertOk()->assertJson(['status' => 'ignored']);

        // Our Razorpay order but the wrong amount or currency.
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment(amount: 1)), 'evt_y')->assertJson(['status' => 'failed']);
        $this->sendWebhook($this->webhookPayload('payment.captured', $this->gatewayPayment(currency: 'INR')), 'evt_z')->assertJson(['status' => 'failed']);

        $this->assertSame(['pending_payment', 'created'], [$this->order->fresh()->payment_status, $this->payment->fresh()->status]);
        $this->assertSame('The payment amount does not match the order. You have not been marked as paid.', WebhookEvent::where('event_id', 'evt_y')->value('error_message'));
    }

    public function test_unhandled_event_types_are_stored_and_ignored(): void
    {
        $this->sendWebhook(['entity' => 'event', 'event' => 'refund.created', 'payload' => []], 'evt_refund')->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame('ignored', WebhookEvent::sole()->processing_status);
    }

    public function test_webhook_route_has_no_login_or_csrf(): void
    {
        $route = \Route::getRoutes()->getByName('webhooks.razorpay');
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware());
        $this->assertContains('throttle:webhooks', $route->gatherMiddleware());
    }
}
