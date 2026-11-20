<?php

namespace Tests\Feature\Admin;

use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Database\DatabaseTestCase;
use Tests\Feature\Payments\PaymentFixtures;

/** Phase 9F: Admin "Orders & Fulfillment" — read-only listing, filters and order details. */
class OrderManagementTest extends DatabaseTestCase
{
    use PaymentFixtures;

    private const ACTIVATION = 'LPA-SECRET-MATCHING-ID-123';

    private const SMDP = 'rsp.secret-smdp.example';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();   // the admin pages must never call eSIM Go or Razorpay
        Queue::fake();                  // ... and must never queue a fulfilment job
    }

    // ------------------------------------------------------------------ fixtures

    private function customer(string $name = 'Asha Customer', ?string $email = null): User
    {
        return User::factory()->create(['name' => $name, 'email' => $email ?? strtolower(str_replace(' ', '.', $name)).'@example.test']);
    }

    private function payment(Order $order, string $status, array $attributes = []): Payment
    {
        static $n = 0;
        $n++;

        return Payment::query()->create($attributes + [
            'order_id' => $order->id, 'user_id' => $order->user_id, 'gateway' => 'razorpay', 'gateway_order_id' => "order_T{$n}",
            'gateway_payment_id' => "pay_T{$n}", 'amount' => $order->total, 'currency' => $order->currency, 'status' => $status,
            'gateway_response' => ['secret_marker' => 'GATEWAY-RAW-PAYLOAD'],
        ]);
    }

    private function paid(Order $order): Order
    {
        $this->payment($order, Payment::STATUS_SUCCEEDED, ['paid_at' => now(), 'verified_at' => now()]);
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID, 'paid_at' => now()])->save();

        return $order;
    }

    private function providerOrder(Order $order, string $status, array $attributes = []): ProviderOrder
    {
        return ProviderOrder::query()->create($attributes + [
            'order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => 'FUL-'.$order->order_number, 'status' => $status,
            'attempts' => 1, 'submitted_at' => now(),
        ]);
    }

    /** One order in each fulfilment stage. @return array<string, Order> */
    private function ordersInEveryStage(): array
    {
        $orders = [];
        $orders['not_started'] = $this->pendingOrder($this->customer('Nina Notstarted'));
        $orders['queued'] = $this->paid($this->pendingOrder($this->customer('Quinn Queued')));

        $orders['processing'] = $this->paid($this->pendingOrder($this->customer('Paula Processing')));
        $this->providerOrder($orders['processing'], ProviderOrder::STATUS_SUBMITTED, ['provider_reference' => 'ref-proc']);
        $orders['processing']->forceFill(['fulfillment_status' => Order::FULFILLMENT_PROCESSING])->save();

        $orders['fulfilled'] = $this->paid($this->pendingOrder($this->customer('Fred Fulfilled')));
        $this->providerOrder($orders['fulfilled'], ProviderOrder::STATUS_COMPLETED, ['provider_reference' => 'ref-done', 'completed_at' => now()]);
        $orders['fulfilled']->forceFill(['fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'fulfilled_at' => now()])->save();

        foreach (['failed' => ProviderOrder::STATUS_FAILED, 'needs_reconciliation' => ProviderOrder::STATUS_NEEDS_RECONCILIATION, 'needs_attention' => ProviderOrder::STATUS_SUBMITTED] as $stage => $poStatus) {
            $orders[$stage] = $this->paid($this->pendingOrder($this->customer('Stage '.ucfirst(str_replace('_', ' ', $stage)))));
            $this->providerOrder($orders[$stage], $poStatus, ['last_error' => "Problem for {$stage}", 'provider_reference' => $poStatus === ProviderOrder::STATUS_FAILED ? null : "ref-{$stage}"]);
            $orders[$stage]->forceFill(['fulfillment_status' => Order::FULFILLMENT_NEEDS_ATTENTION])->save();
        }

        return $orders;
    }

    private function listed(array $query): array
    {
        $html = $this->get(route('admin.orders.index', $query))->assertOk()->getContent();
        preg_match_all('/class="adm-strong-link adm-code">([^<]+)</', $html, $m);

        return $m[1];
    }

    // ------------------------------------------------------------------ access

    public function test_only_admins_can_open_the_order_pages(): void
    {
        $order = $this->pendingOrder($this->customer());

        $this->get('/admin/orders')->assertRedirect('/login');
        $this->get('/admin/orders/'.$order->order_number)->assertRedirect('/login');

        $this->actingAs($this->customer('Other Customer'));
        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/orders/'.$order->order_number)->assertForbidden();
    }

    public function test_sidebar_has_the_orders_entry_and_highlights_it(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('Orders &amp; Fulfillment', false)->assertSee('Provider Bundles');

        $html = $this->get('/admin/orders')->getContent();
        $this->assertMatchesRegularExpression('#<a href="[^"]*/admin/orders"\s+class="adm-sidenav-link is-active"\s+aria-current="page"\s*>#', $html);
    }

    /**
     * Phase 9F part 2: only two audited POST actions were added — no cancel / mark-paid / delete route.
     * Phase 9D adds the QR image (GET) and an audited QR regeneration (POST, no provider call).
     */
    public function test_the_order_routes_are_limited_to_viewing_and_two_audited_actions(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin/orders'))->keyBy->getName();

        // Phase 9E adds an audited "eSIM ready" email retry (email only).
        $this->assertSame(['admin.orders.esims.qr', 'admin.orders.esims.qr.regenerate', 'admin.orders.index', 'admin.orders.notifications.esim-ready', 'admin.orders.reconcile', 'admin.orders.retry', 'admin.orders.show'], $routes->keys()->sort()->values()->all());
        $this->assertSame(['GET', 'HEAD'], $routes['admin.orders.index']->methods());
        $this->assertSame(['GET', 'HEAD'], $routes['admin.orders.show']->methods());
        $this->assertSame(['GET', 'HEAD'], $routes['admin.orders.esims.qr']->methods());
        $this->assertContains('admin', $routes['admin.orders.esims.qr']->gatherMiddleware());
        foreach (['admin.orders.reconcile', 'admin.orders.retry', 'admin.orders.esims.qr.regenerate', 'admin.orders.notifications.esim-ready'] as $name) {
            $this->assertSame(['POST'], $routes[$name]->methods());
            $this->assertContains('admin', $routes[$name]->gatherMiddleware());
            $this->assertContains('throttle:10,1', $routes[$name]->gatherMiddleware());
        }
    }

    // ------------------------------------------------------------------ listing

    public function test_listing_shows_the_required_columns(): void
    {
        $this->actingAs($this->admin());
        $order = $this->paid($this->pendingOrder($this->customer('Asha Customer', 'asha@example.test'), '19.99'));

        $this->get('/admin/orders')->assertOk()
            ->assertSeeInOrder(['Order number', 'Customer name', 'Customer email', 'Order date', 'Order total', 'Currency', 'Payment status', 'Order status', 'Fulfillment status'])
            ->assertSeeInOrder([$order->order_number, 'Asha Customer', 'asha@example.test', $order->created_at->format('d M Y'), '19.99', 'USD', 'Paid / captured', 'Paid', 'Queued', 'View details'])
            ->assertSee(route('admin.orders.show', $order), false)
            ->assertSee('1 of 1 orders shown')
            ->assertDontSee('GATEWAY-RAW-PAYLOAD');
    }

    public function test_empty_states(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/orders')->assertOk()->assertSee('No orders yet.');

        $this->pendingOrder($this->customer());
        $this->get('/admin/orders?q=nothing-matches')->assertOk()->assertSee('No orders match these filters.');
    }

    public function test_search_by_order_number_name_and_email(): void
    {
        $this->actingAs($this->admin());
        $asha = $this->pendingOrder($this->customer('Asha Customer', 'asha@example.test'));
        $ravi = $this->pendingOrder($this->customer('Ravi Kumar', 'ravi@example.test'));
        $billing = $this->pendingOrder($this->customer('Account Holder', 'holder@example.test'), attributes: ['billing_name' => 'Billing Person', 'billing_email' => 'billing@example.test']);

        $this->assertSame([$asha->order_number], $this->listed(['q' => $asha->order_number]));
        $this->assertSame([$ravi->order_number], $this->listed(['q' => 'ravi kum']));
        $this->assertSame([$asha->order_number], $this->listed(['q' => 'asha@example']));
        $this->assertSame([$billing->order_number], $this->listed(['q' => 'Billing Person']));
        $this->assertSame([$billing->order_number], $this->listed(['q' => 'holder@example.test']));   // account email
        $this->assertSame([], $this->listed(['q' => '%']));                                           // wildcards are literal
        $this->assertCount(3, $this->listed(['q' => '   ']));
    }

    public function test_payment_filter_uses_the_latest_payment_attempt(): void
    {
        $this->actingAs($this->admin());
        $none = $this->pendingOrder($this->customer('No Payment'));
        $created = $this->pendingOrder($this->customer('Created Payment'));
        $this->payment($created, Payment::STATUS_CREATED);
        $authorized = $this->pendingOrder($this->customer('Authorized Payment'));
        $this->payment($authorized, Payment::STATUS_PENDING);
        $failed = $this->pendingOrder($this->customer('Failed Payment'));
        $this->payment($failed, Payment::STATUS_FAILED, ['failure_reason' => 'Card declined']);
        $retried = $this->pendingOrder($this->customer('Retried Payment'));
        $this->payment($retried, Payment::STATUS_FAILED);
        $this->paid($retried);
        $refunded = $this->pendingOrder($this->customer('Refunded Payment'));
        $this->payment($refunded, Payment::STATUS_PARTIALLY_REFUNDED);

        $this->assertSame([$none->order_number], $this->listed(['payment' => 'none']));
        $this->assertSame([$created->order_number], $this->listed(['payment' => 'created']));
        $this->assertSame([$authorized->order_number], $this->listed(['payment' => 'authorized']));
        $this->assertSame([$failed->order_number], $this->listed(['payment' => 'failed']));          // not the retried one
        $this->assertSame([$retried->order_number], $this->listed(['payment' => 'succeeded']));
        $this->assertSame([$refunded->order_number], $this->listed(['payment' => 'refunded']));
        $this->assertCount(6, $this->listed(['payment' => 'bogus']));                                 // unknown values are ignored

        $this->get(route('admin.orders.index', ['payment' => 'authorized']))->assertSee('Authorized');
    }

    public function test_order_status_filter_uses_the_existing_order_statuses(): void
    {
        $this->actingAs($this->admin());
        $pending = $this->pendingOrder($this->customer('Pending One'));
        $paid = $this->paid($this->pendingOrder($this->customer('Paid One')));
        $cancelled = $this->pendingOrder($this->customer('Cancelled One'), attributes: ['payment_status' => Order::PAYMENT_CANCELLED, 'cancelled_at' => now()]);

        $this->assertSame([$pending->order_number], $this->listed(['status' => Order::PAYMENT_PENDING]));
        $this->assertSame([$paid->order_number], $this->listed(['status' => Order::PAYMENT_PAID]));
        $this->assertSame([$cancelled->order_number], $this->listed(['status' => Order::PAYMENT_CANCELLED]));
        $this->assertSame([], $this->listed(['status' => Order::PAYMENT_REFUNDED]));
        $this->assertCount(3, $this->listed(['status' => 'shipped']));
    }

    public function test_fulfilment_filter_separates_every_stage(): void
    {
        $this->actingAs($this->admin());
        $orders = $this->ordersInEveryStage();

        foreach ($orders as $stage => $order) {
            $this->assertSame([$order->order_number], $this->listed(['fulfilment' => $stage]), "Filter {$stage}");
        }

        $this->get('/admin/orders')->assertOk()
            ->assertSee('7 of 7 orders shown')
            ->assertSee('Not started')->assertSee('Queued')->assertSee('Processing')->assertSee('Fulfilled')
            ->assertSee('Failed')->assertSee('Needs reconciliation')
            ->assertSee('3 orders need attention.');
    }

    public function test_filters_combine_and_paginate(): void
    {
        $this->actingAs($this->admin());
        $match = $this->paid($this->pendingOrder($this->customer('Combo Match')));
        $this->paid($this->pendingOrder($this->customer('Combo Other')));
        $this->pendingOrder($this->customer('Combo Match Unpaid'));

        $this->assertSame([$match->order_number], $this->listed(['q' => 'Combo Match', 'status' => 'paid', 'payment' => 'succeeded', 'fulfilment' => 'queued']));

        $user = $this->customer('Bulk Buyer');
        for ($i = 0; $i < 26; $i++) {
            $this->pendingOrder($user);
        }
        $this->get('/admin/orders?q=Bulk')->assertOk()->assertSee('Page 1 of 2');
        $this->assertCount(1, $this->listed(['q' => 'Bulk', 'page' => 2]));
    }

    // ------------------------------------------------------------------ details

    public function test_details_show_the_full_lifecycle_of_a_fulfilled_order_without_secrets(): void
    {
        $this->actingAs($this->admin());
        $order = $this->paid($this->pendingOrder($this->customer('Asha Customer', 'asha@example.test')));
        $po = $this->providerOrder($order, ProviderOrder::STATUS_COMPLETED, ['provider_reference' => 'ref-9f-0001', 'total_provider_cost' => '2.0000', 'provider_currency' => 'USD', 'completed_at' => now()]);
        Esim::query()->create([
            'user_id' => $order->user_id, 'order_item_id' => $order->items()->first()->id, 'provider_order_id' => $po->id,
            'iccid' => '8944000000000000099', 'smdp_address' => self::SMDP, 'activation_code' => self::ACTIVATION, 'bundle_code' => 'SECRET_BUNDLE', 'status' => 'assigned',
        ]);
        $order->forceFill(['fulfillment_status' => Order::FULFILLMENT_FULFILLED, 'fulfilled_at' => now()])->save();
        IntegrationLog::query()->create(['service' => 'esimgo', 'operation' => 'fulfilment_completed', 'success' => true, 'order_id' => $order->id,
            'request_body' => ['note' => '1 eSIM(s) stored', 'matchingId' => self::ACTIVATION], 'response_body' => ['smdp' => self::SMDP]]);

        $page = $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSeeInOrder(['Order placed', 'Payment initiated', 'Payment verified', 'Order marked paid', 'Fulfillment queued',
                'eSIM Go purchase', 'ref-9f-0001', 'eSIM details retrieved', '1 of 1 eSIM(s) stored', 'Fulfillment completed', 'Customer receives eSIM details'])
            ->assertSee('Asha Customer')->assertSee('asha@example.test')
            ->assertSee('Test eSIM · 5 GB - 7 Days')->assertSee('SECRET_BUNDLE')
            ->assertSee('Paid / captured')->assertSee('8944000000000000099')
            ->assertSee('fulfilment_completed')->assertSee('1 eSIM(s) stored')
            ->assertDontSee('GATEWAY-RAW-PAYLOAD')
            ->assertDontSee('esimgo:fulfil');

        // Phase 9D: activation details are shown only inside the admin eSIM activation section
        // (never in the integration log, which here even contains them on purpose).
        $outside = preg_replace('#<section[^>]*data-section="esim-activation".*?</section>#s', '', $page->getContent());
        $this->assertStringNotContainsString(self::ACTIVATION, $outside);
        $this->assertStringNotContainsString(self::SMDP, $outside);
        $this->assertStringContainsString(self::ACTIVATION, $page->getContent());
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
    }

    public function test_details_explain_problem_stages_without_offering_a_purchase_button(): void
    {
        $this->actingAs($this->admin());
        $orders = $this->ordersInEveryStage();

        $this->get(route('admin.orders.show', $orders['needs_reconciliation']))->assertOk()
            ->assertSee('may or may not have gone through')->assertSee('Problem for needs_reconciliation')
            ->assertDontSee('--retry-failed')->assertDontSee(route('admin.orders.retry', $orders['needs_reconciliation']), false);

        $this->get(route('admin.orders.show', $orders['failed']))->assertOk()
            ->assertSee('nothing was bought')->assertSee('php artisan esimgo:fulfil '.$orders['failed']->order_number.' --retry-failed');

        $this->get(route('admin.orders.show', $orders['needs_attention']))->assertOk()
            ->assertSee('needs attention')->assertSee('never buys again');

        $this->get(route('admin.orders.show', $orders['queued']))->assertOk()
            ->assertSee('Waiting for the queue worker');

        $this->get(route('admin.orders.show', $orders['not_started']))->assertOk()
            ->assertSee('No payment attempt yet')->assertSee('No eSIM Go order yet');
    }

    public function test_a_skipped_fulfilment_is_shown_with_its_reason(): void
    {
        $this->actingAs($this->admin());
        $order = $this->paid($this->pendingOrder($this->customer()));
        IntegrationLog::query()->create(['service' => 'esimgo', 'operation' => 'fulfilment_skipped', 'success' => false, 'order_id' => $order->id,
            'error_message' => 'eSIM fulfilment is switched off (ESIMGO_FULFILMENT_ENABLED).']);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Skipped: eSIM fulfilment is switched off');
    }

    public function test_unknown_order_is_404(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/orders/EC-DOES-NOT-EXIST')->assertNotFound();
    }

    public function test_viewing_pages_changes_nothing_and_contacts_no_provider(): void
    {
        $this->actingAs($this->admin());
        $orders = $this->ordersInEveryStage();
        $snapshot = fn () => [
            DB::table('orders')->orderBy('id')->get()->toJson(),
            DB::table('payments')->orderBy('id')->get()->toJson(),
            DB::table('provider_orders')->orderBy('id')->get()->toJson(),
            DB::table('esims')->count(), DB::table('integration_logs')->count(),
        ];
        $before = $snapshot();

        $this->get('/admin/orders')->assertOk();
        foreach ($orders as $order) {
            $this->get(route('admin.orders.show', $order))->assertOk();
        }

        $this->assertSame($before, $snapshot());
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
