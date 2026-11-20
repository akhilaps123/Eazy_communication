<?php

namespace Tests\Feature\EsimGo;

use App\Jobs\FulfilOrderJob;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProviderOrder;
use App\Models\User;
use App\Services\EsimGo\FulfilmentService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Database\DatabaseTestCase;
use Tests\Feature\Payments\PaymentFixtures;

/**
 * Phase 9C: paid order → eSIM Go purchase → eSIMs stored. All eSIM Go and
 * Razorpay traffic is faked (Http::preventStrayRequests); nothing real is bought.
 */
class FulfilmentTest extends DatabaseTestCase
{
    use EsimGoFixtures;
    use PaymentFixtures;

    private const MATCHING_ID = 'MATCH-SECRET-0001';

    private const SMDP = 'rsp.secret-smdp.example';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->configureEsimGo(['esimgo.currency' => 'USD']);
        config(['esimgo.fulfilment.enabled' => true, 'esimgo.fulfilment.allow_test_payments' => true]);
    }

    // ------------------------------------------------------------------ fixtures

    /** A paid order whose payment the SERVER verified (as PaymentService leaves it). */
    private function paidOrder(array $lines = [['SECRET_BUNDLE', 1]], string $total = '19.99'): Order
    {
        $order = $this->pendingOrder(User::factory()->create(), $total);
        $order->items()->delete();
        foreach ($lines as [$bundle, $quantity]) {
            OrderItem::query()->create([
                'order_id' => $order->id, 'plan_id' => $this->draftPlan()->id, 'plan_name' => "Plan {$bundle}", 'bundle_code' => $bundle,
                'unit_price' => '1.00', 'quantity' => $quantity, 'line_total' => '1.00', 'unit_provider_cost' => '0.5000', 'provider_currency' => 'USD',
            ]);
        }
        Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay', 'gateway_order_id' => 'order_Paid'.$order->id,
            'gateway_payment_id' => 'pay_Paid'.$order->id, 'amount' => $total, 'currency' => 'USD', 'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(), 'verified_at' => now(),
        ]);
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

        return $order->fresh('items');
    }

    private function esimRow(string $iccid, int $n = 1): array
    {
        return ['iccid' => $iccid, 'matchingId' => self::MATCHING_ID.$n, 'smdpAddress' => self::SMDP];
    }

    private function purchaseBody(array $lines = [['SECRET_BUNDLE', ['8944000000000000001']]], array $overrides = []): array
    {
        $order = [];
        $n = 0;
        foreach ($lines as [$bundle, $iccids]) {
            $esims = [];
            foreach ($iccids as $iccid) {
                $esims[] = $this->esimRow($iccid, ++$n);
            }
            $order[] = ['type' => 'bundle', 'item' => $bundle, 'quantity' => count($iccids), 'subTotal' => 2.0 * count($iccids), 'pricePerUnit' => 2.0,
                'AllowReassign' => false, 'iccids' => $iccids, 'esims' => $esims];
        }

        return $overrides + ['order' => $order, 'total' => 2.0 * $n, 'currency' => 'USD', 'status' => 'completed',
            'statusMessage' => "Order completed: {$n} eSIM assigned", 'orderReference' => 'ref-0001-aaaa', 'createdDate' => '2026-11-19T10:00:00Z', 'assigned' => true];
    }

    private array $answers = [];

    private bool $faked = false;

    /**
     * Fake eSIM Go: validate / purchase / assignments answered separately.
     * Registered once per test; calling again only changes the answers
     * (Laravel keeps the first registered fake, so it must not be stacked).
     */
    private function fakeEsimGo(array $answers = []): void
    {
        $this->answers = $answers;
        if ($this->faked) {
            return;
        }
        $this->faked = true;

        Http::fake(function (Request $request) {
            $answers = $this->answers;
            $url = $request->url();
            if ($url === self::BASE.'/orders') {
                $type = $request->data()['type'] ?? null;
                $answer = $answers[$type] ?? ($type === 'validate'
                    ? Http::response(['order' => [], 'total' => 2.0, 'valid' => true, 'currency' => 'USD', 'assigned' => false])
                    : Http::response($this->purchaseBody()));

                return is_callable($answer) ? $answer($request) : $answer;
            }
            if (str_starts_with($url, self::BASE.'/esims/assignments')) {
                $answer = $answers['assignments'] ?? Http::response([]);

                return is_callable($answer) ? $answer($request) : $answer;
            }

            return null;   // anything else is a stray request → test fails
        });
    }

    private function purchaseRequests(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BASE.'/orders' && ($r->data()['type'] ?? null) === 'transaction')->count();
    }

    private function fulfil(Order $order, bool $retryFailed = false): array
    {
        return app(FulfilmentService::class)->fulfil($order, $retryFailed);
    }

    // ------------------------------------------------------------------ hand-off from payment

    public function test_a_verified_captured_payment_queues_fulfilment_exactly_once(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(User::factory()->create());
        $payment = Payment::query()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay',
            'gateway_order_id' => self::RZP_ORDER, 'amount' => '19.99', 'currency' => 'USD', 'status' => Payment::STATUS_CREATED]);
        $payments = app(PaymentService::class);

        $payments->applyGatewayPayment($payment, $this->gatewayPayment('captured'), 'webhook');
        $payments->applyGatewayPayment($payment, $this->gatewayPayment('captured'), 'webhook');   // duplicate webhook
        $payments->applyGatewayPayment($payment, $this->gatewayPayment('captured'), 'checkout');  // browser verification too

        Queue::assertPushed(FulfilOrderJob::class, 1);
        Queue::assertPushed(FulfilOrderJob::class, fn (FulfilOrderJob $job) => $job->orderId === $order->id);
    }

    public function test_failed_or_authorized_payments_never_queue_fulfilment(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(User::factory()->create());
        $payment = Payment::query()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay',
            'gateway_order_id' => self::RZP_ORDER, 'amount' => '19.99', 'currency' => 'USD', 'status' => Payment::STATUS_CREATED]);

        app(PaymentService::class)->applyGatewayPayment($payment, $this->gatewayPayment('failed'), 'webhook');
        app(PaymentService::class)->applyGatewayPayment($payment, $this->gatewayPayment('authorized', id: 'pay_Auth0001'), 'webhook');

        Queue::assertNothingPushed();
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }

    public function test_duplicate_webhooks_through_the_real_route_buy_only_once(): void
    {
        // Real chain: signed webhook → PaymentService → queued job (sync) → FulfilmentService.
        $order = $this->pendingOrder(User::factory()->create());
        Payment::query()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay',
            'gateway_order_id' => self::RZP_ORDER, 'amount' => '19.99', 'currency' => 'USD', 'status' => Payment::STATUS_CREATED]);
        $this->fakeEsimGo();

        $payload = $this->webhookPayload('payment.captured', $this->gatewayPayment());
        $this->sendWebhook($payload, 'evt_1')->assertOk();
        $this->sendWebhook($payload, 'evt_1')->assertOk();   // same event again
        $this->sendWebhook($this->webhookPayload('order.paid', $this->gatewayPayment(), $this->gatewayOrder()), 'evt_2')->assertOk();

        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame([Order::PAYMENT_PAID, Order::FULFILLMENT_FULFILLED], [$order->fresh()->payment_status, $order->fresh()->fulfillment_status]);
        $this->assertSame(1, ProviderOrder::count());
        $this->assertSame(1, Esim::count());
    }

    // ------------------------------------------------------------------ happy path

    public function test_paid_order_is_bought_once_and_esims_are_stored_encrypted(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo();

        $result = $this->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $result['result']);
        $this->assertSame(1, $this->purchaseRequests());
        Http::assertSent(fn (Request $r) => ($r->data()['type'] ?? null) === 'transaction' && $r->data()['assign'] === true
            && $r->data()['order'] === [['type' => 'bundle', 'quantity' => 1, 'item' => 'SECRET_BUNDLE', 'allowReassign' => false]]);
        // Validation (no charge) always comes first.
        $types = Http::recorded()->map(fn ($pair) => $pair[0]->data()['type'] ?? null)->filter()->values()->all();
        $this->assertSame(['validate', 'transaction'], $types);

        $po = ProviderOrder::sole();
        $this->assertSame([ProviderOrder::STATUS_COMPLETED, 'ref-0001-aaaa', '2.0000', 'USD', 1, 'FUL-'.$order->order_number],
            [$po->status, $po->provider_reference, $po->total_provider_cost, $po->provider_currency, $po->attempts, $po->our_reference]);
        $this->assertNotNull($po->submitted_at);
        $this->assertNotNull($po->completed_at);

        $order->refresh();
        $this->assertSame([Order::FULFILLMENT_FULFILLED, Order::PAYMENT_PAID], [$order->fulfillment_status, $order->payment_status]);
        $this->assertNotNull($order->fulfilled_at);

        $esim = Esim::sole();
        $this->assertSame(['8944000000000000001', self::MATCHING_ID.'1', self::SMDP, 'SECRET_BUNDLE', $order->user_id, $order->items->first()->id],
            [$esim->iccid, $esim->activation_code, $esim->smdp_address, $esim->bundle_code, $esim->user_id, $esim->order_item_id]);
        $raw = (array) DB::table('esims')->first();
        $this->assertStringNotContainsString(self::MATCHING_ID, json_encode($raw));   // encrypted at rest
        $this->assertStringNotContainsString(self::SMDP, json_encode($raw));

        // Logs: every step recorded, no activation secrets, no API key.
        $logs = IntegrationLog::where('service', 'esimgo')->orderBy('id')->pluck('operation')->all();
        $this->assertSame(['fulfilment_started', 'validate_order', 'create_order', 'fulfilment_purchased', 'fulfilment_completed'], $logs);
        $all = json_encode(IntegrationLog::all()->toArray()).json_encode(ProviderOrder::all()->toArray());
        $this->assertStringNotContainsString(self::MATCHING_ID, $all);
        $this->assertStringNotContainsString(self::SMDP, $all);
        $this->assertKeyNeverLogged();
        $this->assertSame([$order->id, $po->id], [IntegrationLog::where('operation', 'create_order')->value('order_id'), IntegrationLog::where('operation', 'create_order')->value('provider_order_id')]);
    }

    public function test_running_again_or_a_duplicate_job_never_buys_twice(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo();

        (new FulfilOrderJob($order->id))->handle(app(FulfilmentService::class));
        (new FulfilOrderJob($order->id))->handle(app(FulfilmentService::class));
        FulfilOrderJob::dispatch($order->id);   // sync queue in tests
        $again = $this->fulfil($order);

        $this->assertSame([FulfilmentService::RESULT_FULFILLED, 'This order is already fulfilled.'], [$again['result'], $again['message']]);
        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame([1, 1], [ProviderOrder::count(), Esim::count()]);
    }

    public function test_a_second_worker_is_kept_out_by_the_lock(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo();
        $lock = Cache::lock('esimgo-fulfil:'.$order->id, 60);
        $lock->get();

        $result = $this->fulfil($order);
        $lock->release();

        $this->assertSame(FulfilmentService::RESULT_BUSY, $result['result']);
        Http::assertNothingSent();
        $this->assertSame(0, ProviderOrder::count());
    }

    public function test_multi_bundle_orders_map_each_esim_to_its_order_item(): void
    {
        $order = $this->paidOrder([['esim_1GB_7D_JP_V2', 2], ['esim_3GB_30D_FR_V2', 1]], '50.00');
        $this->fakeEsimGo(['transaction' => Http::response($this->purchaseBody([
            ['esim_1GB_7D_JP_V2', ['8944000000000000011', '8944000000000000012']],
            ['esim_3GB_30D_FR_V2', ['8944000000000000021']],
        ]))]);

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $this->fulfil($order)['result']);

        Http::assertSent(fn (Request $r) => ($r->data()['type'] ?? null) === 'transaction' && $r->data()['order'] === [
            ['type' => 'bundle', 'quantity' => 2, 'item' => 'esim_1GB_7D_JP_V2', 'allowReassign' => false],
            ['type' => 'bundle', 'quantity' => 1, 'item' => 'esim_3GB_30D_FR_V2', 'allowReassign' => false],
        ]);
        $byItem = Esim::query()->get()->groupBy('order_item_id')->map->pluck('bundle_code')->map->unique()->map->values()->map->all()->values()->all();
        $this->assertEqualsCanonicalizing([['esim_1GB_7D_JP_V2'], ['esim_3GB_30D_FR_V2']], $byItem);
        $this->assertSame(3, Esim::count());
    }

    // ------------------------------------------------------------------ never buy when not allowed

    public function test_nothing_is_bought_while_fulfilment_is_switched_off(): void
    {
        config(['esimgo.fulfilment.enabled' => false]);
        $order = $this->paidOrder();
        $this->fakeEsimGo();

        $result = $this->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_SKIPPED, $result['result']);
        $this->assertStringContainsString('switched off', $result['message']);
        Http::assertNothingSent();
        $this->assertSame([Order::FULFILLMENT_NOT_STARTED, 0], [$order->fresh()->fulfillment_status, ProviderOrder::count()]);
        $this->assertSame('fulfilment_skipped', IntegrationLog::sole()->operation);
    }

    public function test_test_mode_payments_never_buy_a_real_esim_unless_explicitly_allowed(): void
    {
        config(['esimgo.fulfilment.allow_test_payments' => false]);   // razorpay.mode is "test" in these tests
        $order = $this->paidOrder();
        $this->fakeEsimGo();

        $this->assertStringContainsString('Razorpay TEST mode', $this->fulfil($order)['message']);
        Http::assertNothingSent();

        config(['razorpay.mode' => 'live']);
        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $this->fulfil($order)['result']);
    }

    public function test_unpaid_unverified_or_cancelled_orders_are_never_bought(): void
    {
        $this->fakeEsimGo();

        $pending = $this->pendingOrder(User::factory()->create());   // not paid
        $this->assertSame('The order is not paid.', $this->fulfil($pending)['message']);

        $unverified = $this->paidOrder();                            // "paid" without a server-verified payment
        $unverified->payments()->update(['verified_at' => null]);
        $this->assertSame('No server-verified payment was found for this order.', $this->fulfil($unverified)['message']);

        $cancelled = $this->paidOrder();
        $cancelled->forceFill(['cancelled_at' => now()])->save();
        $this->assertSame('The order is cancelled.', $this->fulfil($cancelled)['message']);

        Http::assertNothingSent();
        $this->assertSame(0, ProviderOrder::count());
    }

    // ------------------------------------------------------------------ uncertain outcomes: never repeated

    public function test_a_purchase_timeout_needs_attention_and_is_never_resent(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $first = $this->fulfil($order);
        $second = $this->fulfil($order);
        (new FulfilOrderJob($order->id))->handle(app(FulfilmentService::class));
        $third = $this->fulfil($order, retryFailed: true);   // not a definite failure: --retry-failed does not apply

        $this->assertSame(FulfilmentService::RESULT_NEEDS_ATTENTION, $first['result']);
        $this->assertStringContainsString('may or may not have been processed', $first['message']);
        $this->assertSame([FulfilmentService::RESULT_NEEDS_ATTENTION, FulfilmentService::RESULT_NEEDS_ATTENTION], [$second['result'], $third['result']]);
        $this->assertSame(1, $this->purchaseRequests());
        $this->assertSame([ProviderOrder::STATUS_NEEDS_RECONCILIATION, Order::FULFILLMENT_NEEDS_ATTENTION],
            [ProviderOrder::sole()->status, $order->fresh()->fulfillment_status]);
        $this->assertSame(0, Esim::count());
    }

    public function test_server_errors_and_answers_without_a_reference_are_uncertain_and_not_retried(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::response(['message' => 'Processing'], 503)]);
        $this->assertSame(FulfilmentService::RESULT_NEEDS_ATTENTION, $this->fulfil($order)['result']);
        $this->assertSame(1, $this->purchaseRequests());   // a POST is never retried on 5xx

        $other = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::response($this->purchaseBody(overrides: ['orderReference' => null]))]);
        $this->assertStringContainsString('without an order reference', $this->fulfil($other)['message']);
        $this->assertSame(ProviderOrder::STATUS_NEEDS_RECONCILIATION, $other->fresh()->providerOrder->status);
    }

    public function test_an_interrupted_run_after_sending_is_treated_as_uncertain(): void
    {
        $order = $this->paidOrder();
        ProviderOrder::query()->create(['order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number,
            'status' => ProviderOrder::STATUS_PENDING, 'submitted_at' => now()->subMinute(), 'attempts' => 1]);
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_PROCESSING])->save();
        $this->fakeEsimGo();

        $result = $this->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_NEEDS_ATTENTION, $result['result']);
        Http::assertNothingSent();
        $this->assertSame(ProviderOrder::STATUS_NEEDS_RECONCILIATION, ProviderOrder::sole()->status);
    }

    public function test_a_crashed_job_marks_an_in_flight_purchase_uncertain(): void
    {
        $order = $this->paidOrder();
        ProviderOrder::query()->create(['order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number,
            'status' => ProviderOrder::STATUS_PENDING, 'submitted_at' => now(), 'attempts' => 1]);

        (new FulfilOrderJob($order->id))->failed(new \RuntimeException('worker killed'));

        $this->assertSame([ProviderOrder::STATUS_NEEDS_RECONCILIATION, Order::FULFILLMENT_NEEDS_ATTENTION],
            [ProviderOrder::sole()->status, $order->fresh()->fulfillment_status]);
    }

    // ------------------------------------------------------------------ definite failures: nothing bought

    public function test_insufficient_balance_is_a_safe_failure_and_can_be_retried_deliberately(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::response(['message' => 'Insufficient balance to complete this order'], 400)]);

        $result = $this->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_FAILED, $result['result']);
        $this->assertStringContainsString('Not enough eSIM Go balance — nothing was bought', $result['message']);
        $this->assertSame([ProviderOrder::STATUS_FAILED, Order::FULFILLMENT_NEEDS_ATTENTION], [ProviderOrder::sole()->status, $order->fresh()->fulfillment_status]);

        // Not retried by itself…
        $this->assertSame(FulfilmentService::RESULT_FAILED, $this->fulfil($order)['result']);
        $this->assertSame(1, $this->purchaseRequests());

        // …only on purpose after topping up.
        $this->fakeEsimGo();
        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $this->fulfil($order, retryFailed: true)['result']);
        $this->assertSame([1, 2], [ProviderOrder::count(), ProviderOrder::sole()->attempts]);
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
    }

    public function test_provider_rejections_and_invalid_validation_buy_nothing(): void
    {
        $rejected = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::response(['message' => 'Bundle not found'], 400)]);
        $result = $this->fulfil($rejected);
        $this->assertSame(FulfilmentService::RESULT_FAILED, $result['result']);
        $this->assertStringContainsString('eSIM Go rejected the purchase — nothing was bought', $result['message']);

        $invalid = $this->paidOrder();
        $this->fakeEsimGo(['validate' => Http::response(['order' => [], 'total' => 0, 'valid' => false, 'currency' => 'USD'])]);
        $this->assertStringContainsString('cannot be placed', $this->fulfil($invalid)['message']);
        $this->assertSame(1, $this->purchaseRequests());   // only the first order's (rejected) purchase was ever sent
    }

    public function test_never_buys_when_the_provider_cost_exceeds_what_the_customer_paid(): void
    {
        $order = $this->paidOrder(total: '19.99');
        $this->fakeEsimGo(['validate' => Http::response(['order' => [], 'total' => 25.0, 'valid' => true, 'currency' => 'USD'])]);

        $result = $this->fulfil($order);

        $this->assertSame(FulfilmentService::RESULT_FAILED, $result['result']);
        $this->assertStringContainsString('more than the 19.99 USD paid', $result['message']);
        $this->assertSame(0, $this->purchaseRequests());
    }

    // ------------------------------------------------------------------ eSIM details arriving later

    public function test_esim_details_are_fetched_from_assignments_when_missing_from_the_purchase(): void
    {
        $order = $this->paidOrder();
        $body = $this->purchaseBody();
        $body['order'][0]['esims'] = [];   // bought, details not in the response
        $this->fakeEsimGo([
            'transaction' => Http::response($body),
            'assignments' => Http::response([['iccid' => '8944000000000000001', 'matchingId' => self::MATCHING_ID.'1', 'smdpAddress' => self::SMDP, 'profileStatus' => 'Released']]),
        ]);

        $this->assertSame(FulfilmentService::RESULT_FULFILLED, $this->fulfil($order)['result']);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/esims/assignments?reference=ref-0001-aaaa' && $r->header('Accept') === ['application/json']);
        $this->assertSame(self::MATCHING_ID.'1', Esim::sole()->activation_code);
        $this->assertStringNotContainsString(self::MATCHING_ID, json_encode(IntegrationLog::all()->toArray()));
    }

    public function test_job_keeps_checking_for_details_a_limited_number_of_times_without_buying_again(): void
    {
        $order = $this->paidOrder();
        $body = $this->purchaseBody();
        $body['order'][0]['esims'] = [];
        $this->fakeEsimGo(['transaction' => Http::response($body), 'assignments' => Http::response(['message' => 'down'], 503)]);

        FulfilOrderJob::dispatch($order->id);   // sync queue: re-dispatches run immediately

        $this->assertSame(1, $this->purchaseRequests());
        $checks = Http::recorded(fn (Request $r) => str_contains($r->url(), '/esims/assignments'))->count();
        $this->assertSame(5 * 3, $checks);   // 5 job checks × (1 + 2 read retries)
        $this->assertSame([ProviderOrder::STATUS_SUBMITTED, Order::FULFILLMENT_NEEDS_ATTENTION], [ProviderOrder::sole()->status, $order->fresh()->fulfillment_status]);

        // Later, an admin fetches the details: no new purchase.
        $this->fakeEsimGo(['assignments' => Http::response([['iccid' => '8944000000000000001', 'matchingId' => 'M1', 'smdpAddress' => self::SMDP]])]);
        $this->artisan('esimgo:fulfil', ['order' => $order->order_number])->expectsOutputToContain('FULFILLED')->assertSuccessful();
        $this->assertSame(1, $this->purchaseRequests());   // still only the original purchase for the whole test
        $this->assertSame(Order::FULFILLMENT_FULFILLED, $order->fresh()->fulfillment_status);
    }

    public function test_an_iccid_already_belonging_to_another_order_needs_attention(): void
    {
        $first = $this->paidOrder();
        $this->fakeEsimGo();
        $this->fulfil($first);

        $second = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::response($this->purchaseBody(overrides: ['orderReference' => 'ref-0002-bbbb']))]);   // same ICCID again
        $result = $this->fulfil($second);

        $this->assertSame(FulfilmentService::RESULT_NEEDS_ATTENTION, $result['result']);
        $this->assertStringContainsString('already belongs to another order', $result['message']);
        $this->assertSame(1, Esim::count());
        $this->assertSame($first->id, Esim::sole()->orderItem->order_id);
    }

    // ------------------------------------------------------------------ existing data and commands

    public function test_existing_orders_and_snapshots_are_not_touched(): void
    {
        $untouched = $this->pendingOrder(User::factory()->create());
        $before = [$untouched->fresh()->toArray(), $untouched->items()->first()->toArray()];
        $paid = $this->paidOrder();
        $this->fakeEsimGo();

        $this->fulfil($paid);
        $this->fulfil($untouched);

        $this->assertSame($before, [$untouched->fresh()->toArray(), $untouched->items()->first()->toArray()]);
        $this->assertSame($paid->items->first()->toArray(), $paid->items()->first()->toArray());   // snapshot unchanged
    }

    public function test_fulfil_command_lists_and_reports(): void
    {
        $order = $this->paidOrder();
        $this->fakeEsimGo(['transaction' => Http::failedConnection('timeout')]);
        $this->fulfil($order);

        $this->artisan('esimgo:fulfil', ['--list' => true])->expectsOutputToContain('needs_reconciliation')->assertSuccessful();   // one table row: order, status, …
        $this->artisan('esimgo:fulfil')->expectsOutputToContain($order->order_number)->assertSuccessful();
        $this->artisan('esimgo:fulfil', ['order' => $order->order_number])->expectsOutputToContain('NEEDS_ATTENTION')->assertFailed();
        $this->artisan('esimgo:fulfil', ['order' => 'EC-NOPE'])->expectsOutputToContain('Order not found.')->assertFailed();
        $this->assertSame(1, $this->purchaseRequests());
    }
}
