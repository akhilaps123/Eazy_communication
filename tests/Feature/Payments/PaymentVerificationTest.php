<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Database\DatabaseTestCase;

class PaymentVerificationTest extends DatabaseTestCase
{
    use PaymentFixtures;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->customer = User::factory()->create();
        $this->order = $this->pendingOrder($this->customer);
        $this->actingAs($this->customer);
    }

    /** Create the Razorpay order (mocked) and return a verify request body. */
    private function startAndVerify(?array $payment = null, ?string $signature = null, ?string $orderId = null)
    {
        $this->fakeRazorpay(payment: $payment);
        $this->postJson(route('orders.payment.initiate', $this->order))->assertOk();

        $paymentId = $payment['id'] ?? 'pay_TestPayment001';

        return $this->postJson(route('orders.payment.verify', $this->order), [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => $orderId ?? self::RZP_ORDER,
            'razorpay_signature' => $signature ?? $this->checkoutSignature(self::RZP_ORDER, $paymentId),
        ]);
    }

    public function test_a_valid_signature_and_captured_payment_marks_the_order_paid(): void
    {
        $this->startAndVerify()->assertOk()->assertJson(['success' => true, 'status' => 'paid']);

        $order = $this->order->fresh();
        $payment = Payment::sole();
        $this->assertSame(['paid', 'not_started'], [$order->payment_status, $order->fulfillment_status]);   // fulfilment not started
        $this->assertNotNull($order->paid_at);
        $this->assertSame(['succeeded', 'pay_TestPayment001', 'card'], [$payment->status, $payment->gateway_payment_id, $payment->method]);
        $this->assertNotNull($payment->verified_at);
        $this->assertArrayNotHasKey('card', $payment->gateway_response['payment']);   // no card data stored
        $this->assertArrayNotHasKey('contact', $payment->gateway_response['payment']);
        $this->assertDatabaseCount('provider_orders', 0);                              // no eSIM Go order
        $this->assertDatabaseCount('esims', 0);

        $this->get(route('orders.show', $order->order_number))->assertSee('Paid')->assertDontSee('id="btnPayNow"', false);
    }

    public function test_an_invalid_signature_is_rejected_without_asking_razorpay(): void
    {
        $this->startAndVerify(signature: str_repeat('a', 64))->assertStatus(422)
            ->assertJson(['message' => 'The payment could not be verified (invalid signature). You have not been marked as paid.']);

        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
        Http::assertSentCount(1);   // only the create-order call; the payment was never fetched
    }

    public function test_a_razorpay_order_id_we_did_not_create_is_rejected(): void
    {
        $this->startAndVerify(orderId: 'order_SomeoneElses01', signature: $this->checkoutSignature('order_SomeoneElses01', 'pay_TestPayment001'))
            ->assertStatus(422)->assertJson(['message' => 'This payment does not belong to this order.']);
        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
    }

    public function test_wrong_amount_currency_or_order_from_razorpay_is_rejected(): void
    {
        foreach ([
            $this->gatewayPayment(amount: 1),
            $this->gatewayPayment(currency: 'INR'),
            $this->gatewayPayment(orderId: 'order_DifferentOrd01'),
        ] as $payment) {
            $this->startAndVerify($payment)->assertStatus(422)->assertJsonPath('success', false);
            $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
            $this->assertNotSame('succeeded', Payment::sole()->status);
        }
    }

    public function test_an_authorized_but_not_captured_payment_does_not_mark_the_order_paid(): void
    {
        $this->startAndVerify($this->gatewayPayment('authorized'))->assertOk()->assertJson(['status' => 'processing']);

        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
        $this->assertSame('pending', Payment::sole()->status);
        $this->get(route('orders.show', $this->order->order_number))->assertSee('Processing')->assertDontSee('id="btnPayNow"', false);
    }

    public function test_duplicate_verification_does_not_duplicate_updates(): void
    {
        $this->startAndVerify()->assertOk();
        $paidAt = $this->order->fresh()->paid_at;
        $this->travel(5)->minutes();

        $this->postJson(route('orders.payment.verify', $this->order), [
            'razorpay_payment_id' => 'pay_TestPayment001', 'razorpay_order_id' => self::RZP_ORDER,
            'razorpay_signature' => $this->checkoutSignature(self::RZP_ORDER, 'pay_TestPayment001'),
        ])->assertOk()->assertJson(['status' => 'paid']);

        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals($paidAt, $this->order->fresh()->paid_at);
        $this->postJson(route('orders.payment.initiate', $this->order))->assertStatus(409);   // cannot pay again
    }

    public function test_a_reported_failure_is_confirmed_with_razorpay_and_allows_a_retry(): void
    {
        $this->fakeRazorpay(payment: $this->gatewayPayment('failed'));
        $this->postJson(route('orders.payment.initiate', $this->order))->assertOk();

        $this->postJson(route('orders.payment.failure', $this->order), ['razorpay_payment_id' => 'pay_TestPayment001'])
            ->assertOk()->assertJson(['success' => false, 'status' => 'failed']);

        $this->assertSame(['failed', 'Payment failed (BAD_REQUEST_ERROR)'], [Payment::sole()->status, Payment::sole()->failure_reason]);
        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);   // order kept, not deleted
        $this->get(route('orders.show', $this->order->order_number))->assertSee('Payment failed')->assertSee('Try payment again');
        $this->postJson(route('orders.payment.initiate', $this->order))->assertOk()->assertJson(['order_id' => self::RZP_ORDER]);
    }

    public function test_other_customers_cannot_verify_or_report_on_this_order(): void
    {
        $this->fakeRazorpay();
        $this->postJson(route('orders.payment.initiate', $this->order))->assertOk();

        $this->actingAs(User::factory()->create());
        $this->postJson(route('orders.payment.verify', $this->order), [
            'razorpay_payment_id' => 'pay_TestPayment001', 'razorpay_order_id' => self::RZP_ORDER,
            'razorpay_signature' => $this->checkoutSignature(self::RZP_ORDER, 'pay_TestPayment001'),
        ])->assertNotFound();
        $this->postJson(route('orders.payment.failure', $this->order), ['razorpay_payment_id' => 'pay_TestPayment001'])->assertNotFound();

        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
    }

    public function test_verify_request_fields_are_validated(): void
    {
        $this->postJson(route('orders.payment.verify', $this->order), [
            'razorpay_payment_id' => 'not-a-payment', 'razorpay_order_id' => 'x', 'razorpay_signature' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature']);
    }

    public function test_order_page_payment_section(): void
    {
        // Configured: Pay Now with the official Checkout script, public key only.
        $html = $this->get(route('orders.show', $this->order->order_number))->assertOk()
            ->assertSee('id="btnPayNow"', false)->assertSee('https://checkout.razorpay.com/v1/checkout.js', false)
            ->assertSee('js/order-payment.js', false)->assertSee('Awaiting Payment')->assertSee('$19.99 USD')
            ->getContent();
        $this->assertStringNotContainsString(self::KEY_SECRET, $html);
        $this->assertStringNotContainsString(self::WEBHOOK_SECRET, $html);

        // Not configured: the existing "available soon" message and no gateway script.
        $this->configureRazorpay(['razorpay.key_id' => '']);
        $this->get(route('orders.show', $this->order->order_number))
            ->assertSee('Payment will be available soon.')->assertDontSee('checkout.razorpay.com', false)->assertDontSee('id="btnPayNow"', false);

        // Currency the account does not take: explained, never converted.
        $this->configureRazorpay(['razorpay.currency' => 'INR']);
        $this->get(route('orders.show', $this->order->order_number))
            ->assertSee('Online payment is not available for USD orders yet.')->assertDontSee('id="btnPayNow"', false);
    }
}
