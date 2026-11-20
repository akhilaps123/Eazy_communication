<?php

namespace Tests\Feature\Admin;

use App\Jobs\FulfilOrderJob;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderOrder;
use App\Models\User;
use App\Services\EsimGo\FulfilmentService;
use App\Support\AdminAudit;
use App\Support\OrderLifecycle;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Database\DatabaseTestCase;
use Tests\Feature\EsimGo\EsimGoFixtures;
use Tests\Feature\Payments\PaymentFixtures;

/**
 * Phase 9F part 2: dashboard overview, audit trail, read-only reconciliation and
 * deliberate retry of confirmed failed purchases. eSIM Go is always faked.
 */
class OrderActionsTest extends DatabaseTestCase
{
    use EsimGoFixtures;
    use PaymentFixtures;

    private const MATCHING_ID = 'MATCH-SECRET-9F01';

    private const SMDP = 'rsp.secret-smdp-9f.example';

    private const REF = '3f1c2b7a-9d4e-4c1a-8b2f-5e6d7c8b9a01';

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->configureEsimGo(['esimgo.currency' => 'USD']);
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);

        // One fake for the whole test; answers are switched through $this->answers.
        Http::fake(function (Request $request) {
            $url = $request->url();
            if ($url === self::BASE.'/orders') {
                $type = $request->data()['type'] ?? null;
                $answer = $this->answers[$type] ?? ($type === 'validate'
                    ? Http::response(['order' => [], 'total' => 2.0, 'valid' => true, 'currency' => 'USD', 'assigned' => false])
                    : Http::response($this->purchaseBody()));

                return $answer;
            }
            if (str_starts_with($url, self::BASE.'/esims/assignments')) {
                return $this->answers['assignments'] ?? Http::response([]);
            }

            return null;   // anything else is a stray request → the test fails
        });
    }

    // ------------------------------------------------------------------ fixtures

    private function purchaseBody(string $iccid = '8944000000000000901'): array
    {
        return ['order' => [['type' => 'bundle', 'item' => 'SECRET_BUNDLE', 'quantity' => 1, 'subTotal' => 2.0, 'pricePerUnit' => 2.0,
            'iccids' => [$iccid], 'esims' => [$this->esimRow($iccid)]]],
            'total' => 2.0, 'currency' => 'USD', 'status' => 'completed', 'statusMessage' => 'Order completed', 'orderReference' => self::REF, 'assigned' => true];
    }

    private function esimRow(string $iccid = '8944000000000000901'): array
    {
        return ['iccid' => $iccid, 'matchingId' => self::MATCHING_ID, 'smdpAddress' => self::SMDP];
    }

    private function paidOrder(string $total = '19.99'): Order
    {
        $order = $this->pendingOrder(User::factory()->create(), $total);
        Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay', 'gateway_order_id' => 'order_9F'.$order->id,
            'gateway_payment_id' => 'pay_9F'.$order->id, 'amount' => $total, 'currency' => 'USD', 'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(), 'verified_at' => now(),
        ]);
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

        return $order->fresh();
    }

    private function withProviderOrder(Order $order, string $status, ?string $reference = null, string $orderStatus = Order::FULFILLMENT_NEEDS_ATTENTION): Order
    {
        ProviderOrder::query()->create([
            'order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number, 'status' => $status,
            'provider_reference' => $reference, 'attempts' => 1, 'submitted_at' => now()->subHour(),
            'last_error' => $status === ProviderOrder::STATUS_FAILED ? 'Not enough eSIM Go balance — nothing was bought.' : null,
        ]);
        $order->forceFill(['fulfillment_status' => $orderStatus])->save();

        return $order->fresh();
    }

    private function failedOrder(): Order
    {
        return $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_FAILED);
    }

    private function purchaseRequests(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BASE.'/orders' && ($r->data()['type'] ?? null) === 'transaction')->count();
    }

    private function validateRequests(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BASE.'/orders' && ($r->data()['type'] ?? null) === 'validate')->count();
    }

    private function audit(Order $order): array
    {
        return IntegrationLog::query()->where('order_id', $order->id)->where('service', 'admin')->orderBy('id')->pluck('operation')->all();
    }

    private function refused($response, string $needle): void
    {
        $response->assertSessionHasErrors('action');
        $this->assertStringContainsString($needle, session('errors')->first('action'));
    }

    private function retry(Order $order, array $data = ['confirm' => '1'])
    {
        return $this->post(route('admin.orders.retry', $order), $data);
    }

    private function reconcile(Order $order, array $data = [])
    {
        return $this->post(route('admin.orders.reconcile', $order), $data);
    }

    // ------------------------------------------------------------------ dashboard

    public function test_dashboard_order_figures_come_from_the_database_and_link_to_matching_filters(): void
    {
        $this->actingAs($this->admin());
        $this->pendingOrder(User::factory()->create());
        $this->pendingOrder(User::factory()->create());
        $this->paidOrder();                                                                                            // queued
        $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_PENDING, null, Order::FULFILLMENT_PROCESSING); // processing
        $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_COMPLETED, self::REF, Order::FULFILLMENT_FULFILLED);
        $this->failedOrder();
        $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_NEEDS_RECONCILIATION);
        $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_SUBMITTED, 'ref-attention');

        DB::enableQueryLog();
        $overview = OrderLifecycle::overview();
        $this->assertCount(1, DB::getQueryLog(), 'The overview is one aggregate query.');
        DB::disableQueryLog();
        $this->assertSame(['total' => 8, 'pending_payment' => 2, 'awaiting' => 2, 'fulfilled' => 1, 'failed' => 1, 'attention' => 2], $overview);

        $html = $this->get('/admin')->assertOk()->assertSee('Order &amp; fulfillment overview', false)->getContent();
        $links = ['total' => [], 'pending_payment' => ['status' => 'pending_payment'], 'awaiting' => ['fulfilment' => 'awaiting'],
            'fulfilled' => ['fulfilment' => 'fulfilled'], 'failed' => ['fulfilment' => 'failed'], 'attention' => ['fulfilment' => 'attention']];
        foreach ($links as $key => $query) {
            $href = route('admin.orders.index', $query);
            $this->assertMatchesRegularExpression('#<a class="adm-card adm-kpi" href="'.preg_quote(e($href), '#').'" data-kpi="orders-'.$key.'">.*?<span class="adm-kpi-value">'.$overview[$key].'</span>#s', $html, $key);
            // The linked listing shows exactly the counted orders.
            $this->get($href)->assertOk()->assertSee($overview[$key].' of 8 orders shown');
        }

        // Existing catalogue cards are still there.
        foreach (['countries', 'plans', 'sellable', 'pending'] as $kpi) {
            $this->assertStringContainsString('data-kpi="'.$kpi.'"', $html);
        }
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ access & audit

    public function test_customers_and_guests_cannot_run_actions_or_create_audit_entries(): void
    {
        $order = $this->failedOrder();

        $this->retry($order)->assertRedirect('/login');
        $this->reconcile($order)->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->retry($order)->assertForbidden();
        $this->reconcile($order)->assertForbidden();

        $this->assertSame([], $this->audit($order));
        $this->assertSame(ProviderOrder::STATUS_FAILED, $order->providerOrder->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_actions_require_csrf_tokens(): void
    {
        $this->actingAs($this->admin());
        $order = $this->failedOrder();

        // The CSRF middleware skips itself while running unit tests; switch the real check back on.
        $this->app->instance('env', 'production');
        $status = $this->call('POST', route('admin.orders.retry', $order), ['confirm' => '1'])->status();
        $this->app->instance('env', 'testing');

        $this->assertSame(419, $status);
        $this->assertSame([], $this->audit($order));
        Http::assertNothingSent();
    }

    public function test_audit_entries_record_who_what_and_state_without_secrets(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $order = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_SUBMITTED, self::REF);
        $this->answers['assignments'] = Http::response([$this->esimRow()]);

        $secretReason = 'Customer asked. api_key=SUPERSECRET123 Bearer abc.def.ghi LPA:1$rsp.example$'.self::MATCHING_ID.' '.str_repeat('k', 40).' '.self::API_KEY;
        $this->reconcile($order, ['reason' => $secretReason])->assertRedirect(route('admin.orders.show', $order))->assertSessionHas('status');

        $entries = IntegrationLog::query()->where('service', 'admin')->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame(['reconciliation_requested', 'reconciliation_completed'], $entries->pluck('operation')->all());
        $done = $entries->last();
        $this->assertTrue($done->success);
        $this->assertSame($admin->id, $done->request_body['admin_user_id']);
        $this->assertSame($order->providerOrder->id, $done->provider_order_id);
        $this->assertSame(['payment_status' => 'paid', 'fulfillment_status' => 'needs_attention', 'provider_order_status' => 'submitted'], $done->response_body['previous_state']);
        $this->assertSame(['payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'provider_order_status' => 'completed'], $done->response_body['new_state']);
        $this->assertNotNull($done->created_at);
        $this->assertStringStartsWith('Customer asked.', $done->request_body['reason']);

        $raw = json_encode(DB::table('integration_logs')->where('service', 'admin')->get());
        foreach (['SUPERSECRET123', 'abc.def.ghi', 'LPA:1', self::MATCHING_ID, self::SMDP, str_repeat('k', 40), self::API_KEY] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, $secret);
        }

        // History is shown on the order page (reason scrubbed, no secrets).
        $page = $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSeeInOrder(['Action history', 'Reconciliation completed', 'Reconciliation requested'])
            ->assertSee($admin->name)->assertSee('needs_attention / submitted → fulfilled / completed')
            ->assertDontSee('SUPERSECRET123');
        // Phase 9D: activation details appear ONLY in the admin eSIM activation section, never in history or logs.
        $outside = preg_replace('#<section[^>]*data-section="esim-activation".*?</section>#s', '', $page->getContent());
        $this->assertStringNotContainsString(self::MATCHING_ID, $outside);
        $this->assertStringNotContainsString(self::SMDP, $outside);
    }

    public function test_clean_removes_credentials_but_keeps_ordinary_text(): void
    {
        $this->assertNull(AdminAudit::clean('   '));
        $this->assertSame('Balance topped up by finance', AdminAudit::clean("Balance  topped up\nby finance"));
        $this->assertSame('password=[removed] and Bearer [removed]', AdminAudit::clean('password: hunter2 and Bearer xyz'));
        $this->assertSame('eSIM Go order '.self::REF.' checked', AdminAudit::clean('eSIM Go order '.self::REF.' checked'));   // UUID references are kept
        $this->assertSame(500, mb_strlen(AdminAudit::clean(str_repeat('a b ', 400))));
    }

    // ------------------------------------------------------------------ reconciliation

    public function test_reconciliation_fetches_existing_results_and_never_purchases(): void
    {
        $this->actingAs($this->admin());
        $order = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_SUBMITTED, self::REF);
        $this->answers['assignments'] = Http::response([$this->esimRow()]);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Reconcile now')->assertSee(route('admin.orders.reconcile', $order), false);
        $this->reconcile($order)->assertSessionHas('status', fn ($m) => str_contains($m, 'Order fulfilled'));

        $order->refresh();
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fulfillment_status);
        $this->assertSame(ProviderOrder::STATUS_COMPLETED, $order->providerOrder->status);
        $this->assertSame(1, Esim::query()->count());
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/esims/assignments') && str_contains($r->url(), self::REF));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');   // neither validate nor purchase

        // A second request is refused: already fulfilled.
        $this->reconcile($order)->assertSessionHasErrors('action');
        $this->assertSame(['reconciliation_requested', 'reconciliation_completed', 'reconciliation_requested', 'action_blocked'], $this->audit($order));
        $this->assertSame(1, Http::recorded()->count());
    }

    public function test_an_uncertain_purchase_without_reference_cannot_be_reconciled_or_resent(): void
    {
        $this->actingAs($this->admin());
        $order = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_NEEDS_RECONCILIATION);

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('No eSIM Go order reference was recorded')
            ->assertSee('may have gone through')
            ->assertDontSee(route('admin.orders.reconcile', $order), false)
            ->assertDontSee(route('admin.orders.retry', $order), false);

        $this->reconcile($order)->assertSessionHasErrors('action');
        $this->retry($order)->assertSessionHasErrors('action');

        $this->assertSame(ProviderOrder::STATUS_NEEDS_RECONCILIATION, $order->providerOrder->fresh()->status);
        $this->assertSame(['reconciliation_requested', 'action_blocked', 'retry_requested', 'retry_rejected'], $this->audit($order));
        Http::assertNothingSent();
    }

    public function test_an_uncertain_purchase_with_a_reference_is_completed_only_when_esim_go_confirms_it(): void
    {
        $this->actingAs($this->admin());
        $order = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_NEEDS_RECONCILIATION, self::REF);

        // eSIM Go returns nothing yet: nothing changes, nothing is bought.
        $this->reconcile($order)->assertSessionHasErrors('action');
        $this->assertSame(ProviderOrder::STATUS_NEEDS_RECONCILIATION, $order->providerOrder->fresh()->status);
        $this->assertSame(Order::FULFILLMENT_NEEDS_ATTENTION, $order->fresh()->fulfillment_status);

        // eSIM Go confirms the eSIMs of that order: it is completed.
        $this->answers['assignments'] = Http::response([$this->esimRow()]);
        $this->reconcile($order)->assertSessionHas('status');
        $this->assertSame(ProviderOrder::STATUS_COMPLETED, $order->providerOrder->fresh()->status);
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);

        $this->assertSame(['reconciliation_requested', 'reconciliation_incomplete', 'reconciliation_requested', 'reconciliation_completed'], $this->audit($order));
        $this->assertSame(0, $this->purchaseRequests());
        $this->assertSame(0, $this->validateRequests());
    }

    public function test_concurrent_reconciliation_is_blocked_by_the_fulfilment_lock(): void
    {
        $this->actingAs($this->admin());
        $order = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_SUBMITTED, self::REF);
        $lock = Cache::lock('esimgo-fulfil:'.$order->id, 60);
        $this->assertTrue($lock->get());

        $this->reconcile($order)->assertSessionHasErrors(['action' => 'Reconciliation: Another fulfilment run is already in progress for this order.']);
        $this->retry($this->failedOrder())->assertSessionHas('status');   // other orders are not blocked

        $lock->release();
        $this->assertSame(['reconciliation_requested', 'action_blocked'], $this->audit($order));
        $this->assertSame(ProviderOrder::STATUS_SUBMITTED, $order->providerOrder->fresh()->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), self::REF));
    }

    // ------------------------------------------------------------------ retry: refused

    public function test_retry_is_refused_for_orders_that_are_not_confirmed_failures(): void
    {
        $this->actingAs($this->admin());

        $unpaid = $this->withProviderOrder($this->pendingOrder(User::factory()->create()), ProviderOrder::STATUS_FAILED);
        $authorized = $this->pendingOrder(User::factory()->create());
        Payment::query()->create(['order_id' => $authorized->id, 'user_id' => $authorized->user_id, 'gateway' => 'razorpay', 'gateway_payment_id' => 'pay_Auth9F',
            'amount' => '19.99', 'currency' => 'USD', 'status' => Payment::STATUS_PENDING]);
        $authorized = $this->withProviderOrder($authorized, ProviderOrder::STATUS_FAILED);
        $cancelled = $this->failedOrder();
        $cancelled->forceFill(['cancelled_at' => now()])->save();
        $fulfilled = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_COMPLETED, self::REF, Order::FULFILLMENT_FULFILLED);
        $uncertain = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_NEEDS_RECONCILIATION, 'ref-uncertain');
        $bought = $this->withProviderOrder($this->paidOrder(), ProviderOrder::STATUS_SUBMITTED, 'ref-bought');
        $noPurchase = $this->paidOrder();

        $expect = [
            [$unpaid, 'The order is not paid.'],
            [$authorized, 'The order is not paid.'],
            [$cancelled, 'The order is cancelled.'],
            [$fulfilled, 'This order is already fulfilled.'],
            [$uncertain, 'outcome uncertain'],
            [$bought, 'were already bought'],
            [$noPurchase, 'There is no failed purchase to retry.'],
        ];
        foreach ($expect as [$order, $message]) {
            $before = [$order->fresh()->toArray(), $order->providerOrder?->fresh()?->toArray()];
            $this->get(route('admin.orders.show', $order))->assertOk()->assertDontSee(route('admin.orders.retry', $order), false);
            $this->refused($this->retry($order), $message);
            $this->assertSame($before, [$order->fresh()->toArray(), $order->providerOrder?->fresh()?->toArray()], $order->order_number);
            $this->assertSame(['retry_requested', 'retry_rejected'], $this->audit($order));
        }
        Http::assertNothingSent();
    }

    public function test_retry_requires_the_confirmation_box(): void
    {
        $this->actingAs($this->admin());
        $order = $this->failedOrder();

        $this->retry($order, ['reason' => 'no tick'])->assertSessionHasErrors(['confirm' => 'Tick the box to confirm the retry.']);
        $this->retry($order, ['confirm' => '1', 'reason' => str_repeat('x', 501)])->assertSessionHasErrors('reason');

        $this->assertSame(ProviderOrder::STATUS_FAILED, $order->providerOrder->fresh()->status);
        $this->assertSame([], $this->audit($order));
        Http::assertNothingSent();
    }

    public function test_feature_flags_are_always_respected(): void
    {
        $this->actingAs($this->admin());
        $order = $this->failedOrder();

        config(['esimgo.fulfilment.enabled' => false]);
        $this->get(route('admin.orders.show', $order))->assertSee('eSIM fulfilment is switched off')->assertDontSee(route('admin.orders.retry', $order), false);
        $this->refused($this->retry($order), 'switched off');

        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => false]);   // Razorpay is in test mode
        $this->refused($this->retry($order), 'TEST mode');

        $this->assertSame(ProviderOrder::STATUS_FAILED, $order->providerOrder->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_bundle_and_cost_validation_cannot_be_bypassed(): void
    {
        $this->actingAs($this->admin());
        $order = $this->failedOrder();   // customer paid 19.99 USD

        $this->answers['validate'] = Http::response(['order' => [], 'total' => 25.0, 'valid' => true, 'currency' => 'USD']);
        $this->refused($this->retry($order), 'more than the 19.99 USD paid');

        $this->answers['validate'] = Http::response(['order' => [], 'total' => 0, 'valid' => false, 'currency' => 'USD']);
        $this->refused($this->retry($order), 'cannot be placed');

        $this->answers['validate'] = Http::response(['order' => [], 'total' => 2.0, 'valid' => true, 'currency' => 'EUR']);
        $this->refused($this->retry($order), 'cost cannot be checked');

        $this->assertSame(ProviderOrder::STATUS_FAILED, $order->providerOrder->fresh()->status);
        $this->assertSame(3, $this->validateRequests());
        $this->assertSame(0, $this->purchaseRequests());
    }

    // ------------------------------------------------------------------ retry: allowed

    public function test_a_confirmed_failure_can_be_retried_deliberately_and_only_once(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $order = $this->failedOrder();

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Retry purchase')->assertSee(route('admin.orders.retry', $order), false)
            ->assertSee('I checked the cause of the failure');

        // QUEUE_CONNECTION=sync in tests: the queued job runs straight away.
        $this->retry($order, ['confirm' => '1', 'reason' => 'eSIM Go balance topped up'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Retry queued'));

        $order->refresh();
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fulfillment_status);
        $this->assertSame(ProviderOrder::STATUS_COMPLETED, $order->providerOrder->status);
        $this->assertSame(2, $order->providerOrder->attempts);
        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame(2, $this->validateRequests());   // before queuing and again before buying
        $this->assertSame(['retry_requested', 'retry_queued'], $this->audit($order));
        $queued = IntegrationLog::query()->where('service', 'admin')->where('operation', 'retry_queued')->sole();
        $this->assertSame('eSIM Go balance topped up', $queued->request_body['reason']);
        $this->assertSame('failed', $queued->response_body['previous_state']['provider_order_status']);

        // Submitting again (double click, back button) never buys again.
        $this->retry($order)->assertSessionHasErrors('action');
        $this->assertSame(1, $this->purchaseRequests());
    }

    public function test_duplicate_submissions_while_a_retry_is_queued_are_refused(): void
    {
        Queue::fake();
        $this->actingAs($this->admin());
        $order = $this->failedOrder();

        $this->retry($order)->assertSessionHas('status');
        $this->refused($this->retry($order), 'already queued or in progress');

        Queue::assertPushed(FulfilOrderJob::class, 1);
        $this->assertSame(ProviderOrder::STATUS_PENDING, $order->providerOrder->fresh()->status);
        $this->assertNull($order->providerOrder->fresh()->submitted_at);
        $this->assertSame(Order::FULFILLMENT_PROCESSING, $order->fresh()->fulfillment_status);
        $this->assertSame(['retry_requested', 'retry_queued', 'retry_requested', 'retry_rejected'], $this->audit($order));
        $this->assertSame(0, $this->purchaseRequests());

        // Running the queued job twice still buys once.
        (new FulfilOrderJob($order->id))->handle(app(FulfilmentService::class));
        (new FulfilOrderJob($order->id))->handle(app(FulfilmentService::class));
        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
    }

    public function test_a_queued_retry_is_not_sent_if_fulfilment_is_switched_off_before_the_job_runs(): void
    {
        Queue::fake();
        $this->actingAs($this->admin());
        $order = $this->failedOrder();
        $this->retry($order)->assertSessionHas('status');

        config(['esimgo.fulfilment.enabled' => false]);
        (new FulfilOrderJob($order->id))->handle(app(FulfilmentService::class));

        // Back to "failed" (nothing was sent), so it can be retried later — never stuck as pending.
        $po = $order->providerOrder->fresh();
        $this->assertSame(ProviderOrder::STATUS_FAILED, $po->status);
        $this->assertStringContainsString('Not sent to eSIM Go: eSIM fulfilment is switched off', $po->last_error);
        $this->assertSame(Order::FULFILLMENT_NEEDS_ATTENTION, $order->fresh()->fulfillment_status);
        $this->assertSame(0, $this->purchaseRequests());
    }

    public function test_an_uncertain_retry_outcome_is_never_resent(): void
    {
        $this->actingAs($this->admin());
        $order = $this->failedOrder();
        $this->answers['transaction'] = Http::response(['message' => 'gateway timeout'], 503);

        $this->retry($order)->assertSessionHas('status');   // queued; the job then hits a 503

        $this->assertSame(ProviderOrder::STATUS_NEEDS_RECONCILIATION, $order->providerOrder->fresh()->status);
        $this->refused($this->retry($order), 'outcome uncertain');
        $this->reconcile($order)->assertSessionHasErrors('action');   // no reference to look up
        $this->assertSame(1, $this->purchaseRequests());
    }
}
