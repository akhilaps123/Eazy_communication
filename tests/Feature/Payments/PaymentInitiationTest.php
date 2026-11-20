<?php

namespace Tests\Feature\Payments;

use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Database\DatabaseTestCase;

class PaymentInitiationTest extends DatabaseTestCase
{
    use PaymentFixtures;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->customer = User::factory()->create(['name' => 'Asha Customer', 'email' => 'asha@example.test']);
        $this->order = $this->pendingOrder($this->customer);
    }

    private function initiate(?Order $order = null, array $body = [])
    {
        return $this->postJson(route('orders.payment.initiate', $order ?? $this->order), $body);
    }

    public function test_customer_can_start_payment_for_their_pending_order(): void
    {
        $this->fakeRazorpay();

        $response = $this->actingAs($this->customer)->initiate(body: ['amount' => 1, 'currency' => 'INR'])   // ignored
            ->assertOk()
            ->assertExactJson([
                'success' => true, 'key' => self::KEY_ID, 'order_id' => self::RZP_ORDER, 'amount' => 1999, 'currency' => 'USD',
                'name' => 'Eazy Communication', 'description' => 'Order '.$this->order->order_number,
                'order_number' => $this->order->order_number, 'prefill' => ['name' => 'Asha Customer', 'email' => 'asha@example.test'],
            ]);
        $this->assertStringNotContainsString(self::KEY_SECRET, $response->getContent());

        Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://api.razorpay.com/v1/orders'
            && $r['amount'] === 1999 && $r['currency'] === 'USD' && $r['receipt'] === $this->order->order_number
            && $r->hasHeader('Authorization', 'Basic '.base64_encode(self::KEY_ID.':'.self::KEY_SECRET)));

        $payment = Payment::sole();
        $this->assertSame(['razorpay', self::RZP_ORDER, '19.99', 'USD', 'created', $this->customer->id], [
            $payment->gateway, $payment->gateway_order_id, $payment->amount, $payment->currency, $payment->status, $payment->user_id,
        ]);
        $this->assertSame('pending_payment', $this->order->fresh()->payment_status);
    }

    public function test_guests_cannot_start_a_payment(): void
    {
        $this->initiate()->assertUnauthorized();
        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customers_cannot_pay_for_another_customers_order(): void
    {
        $this->actingAs(User::factory()->create())->initiate()->assertNotFound();
        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_orders_cannot_be_paid_again(): void
    {
        $this->order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

        $this->actingAs($this->customer)->initiate()->assertStatus(409)->assertJson(['success' => false, 'message' => 'This order has already been paid.']);
        Http::assertNothingSent();
    }

    public function test_invalid_orders_cannot_start_a_payment(): void
    {
        $this->actingAs($this->customer)->postJson('/orders/EC-000000-NOPE00/payment')->assertNotFound();

        $cancelled = $this->pendingOrder($this->customer, attributes: ['cancelled_at' => now()]);
        $this->initiate($cancelled)->assertStatus(422)->assertJson(['message' => 'This order cannot be paid.']);

        $free = $this->pendingOrder($this->customer, '0.00');
        $this->initiate($free)->assertStatus(422)->assertJson(['message' => 'This order has no amount to pay.']);

        Http::assertNothingSent();
    }

    public function test_currency_mismatch_and_missing_or_live_keys_are_refused_without_converting(): void
    {
        $this->configureRazorpay(['razorpay.currency' => 'INR']);
        $this->actingAs($this->customer)->initiate()->assertStatus(422)
            ->assertJson(['message' => 'Online payment is not available for USD orders yet. You have not been charged.']);

        $this->configureRazorpay(['razorpay.key_secret' => '']);
        $this->initiate()->assertStatus(422)->assertJson(['message' => 'Online payment is not available yet.']);

        $this->configureRazorpay(['razorpay.key_id' => 'rzp_live_RealKey123']);   // live keys are never used
        $this->initiate()->assertStatus(422);

        $this->configureRazorpay(['razorpay.mode' => 'live']);
        $this->initiate()->assertStatus(422);

        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_gateway_order_with_the_wrong_amount_or_currency_is_rejected(): void
    {
        $this->fakeRazorpay(order: $this->gatewayOrder(amount: 100));
        $this->actingAs($this->customer)->initiate()->assertStatus(502)->assertJson(['success' => false]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_gateway_currency_rejection_is_reported_safely(): void
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'Currency is not supported']], 400)]);

        $this->actingAs($this->customer)->initiate()->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'The payment gateway declined the request: Currency is not supported']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_repeated_clicks_reuse_one_gateway_order(): void
    {
        $this->fakeRazorpay();
        $this->actingAs($this->customer);

        $first = $this->initiate()->json('order_id');
        $second = $this->initiate()->json('order_id');
        $this->assertSame($first, $second);

        Payment::sole()->update(['status' => Payment::STATUS_FAILED]);   // retry after a failure
        $this->assertSame($first, $this->initiate()->json('order_id'));

        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_an_authorized_payment_blocks_a_second_payment(): void
    {
        $this->fakeRazorpay();
        $this->actingAs($this->customer)->initiate();
        Payment::sole()->update(['status' => Payment::STATUS_PENDING]);

        $this->initiate()->assertStatus(409)->assertJsonPath('success', false);
        Http::assertSentCount(1);
    }

    public function test_gateway_calls_are_logged_without_secrets(): void
    {
        $this->fakeRazorpay();
        $this->actingAs($this->customer)->initiate();

        $log = IntegrationLog::sole();
        $this->assertSame(['payment', 'razorpay_create_order', true, 200], [$log->service, $log->operation, $log->success, $log->status_code]);
        $this->assertStringNotContainsString(self::KEY_SECRET, json_encode($log->getAttributes()));
    }

    public function test_payment_routes_need_login_csrf_and_are_rate_limited(): void
    {
        foreach (['orders.payment.initiate', 'orders.payment.verify', 'orders.payment.failure'] as $name) {
            $middleware = \Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('throttle:payment', $middleware);
        }
    }
}
