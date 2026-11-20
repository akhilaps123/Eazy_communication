<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\ProviderBundle;
use App\Models\User;
use App\Services\EsimGo\CatalogueSynchroniser;
use Database\Factories\PlanPriceFactory;
use Database\Factories\ProviderBundleFactory;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;
use Tests\Feature\EsimGo\EsimGoFixtures;

/** Phase 9B-1: Provider Bundles admin module, creating plans from existing bundles, re-linking. */
class ProviderBundleManagementTest extends DatabaseTestCase
{
    use CatalogueFixtures;
    use EsimGoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
    }

    private function synced(array $attributes = []): ProviderBundle
    {
        return ProviderBundleFactory::new()->create($attributes + ['last_synced_at' => now()]);
    }

    private function planForm(array $overrides = []): array
    {
        $overrides['countries'] ??= [$this->country()->id];

        return $overrides + [
            'name' => 'New plan', 'data_type' => 'fixed', 'data_amount' => '1', 'data_unit' => 'GB',
            'validity_days' => '7', 'coverage_type' => 'local',
        ];
    }

    /** A paid historical order for a plan, with its snapshot. */
    private function orderFor(Plan $plan): OrderItem
    {
        $customer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'EC-TEST-'.$plan->id, 'user_id' => $customer->id, 'currency' => 'USD', 'subtotal' => '10.00',
            'total' => '10.00', 'billing_name' => $customer->name, 'billing_email' => $customer->email,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'plan_id' => $plan->id, 'plan_name' => $plan->name, 'bundle_code' => $plan->providerBundle->bundle_code,
            'unit_price' => '10.00', 'quantity' => 1, 'line_total' => '10.00',
            'unit_provider_cost' => $plan->providerBundle->provider_cost, 'provider_currency' => $plan->providerBundle->provider_currency,
        ]);
    }

    // ------------------------------------------------------------------ listing

    public function test_admin_sees_the_provider_bundle_listing_with_provider_information(): void
    {
        $this->synced(['bundle_code' => 'esim_1GB_7D_JP_V2', 'description' => 'eSIM, 1GB, 7 Days, Japan', 'country_codes' => ['JP'],
            'data_amount_mb' => 1000, 'duration_days' => 7, 'speed' => '4G/5G', 'provider_cost' => '1.2300', 'provider_currency' => 'USD']);
        ProviderBundleFactory::new()->unavailable()->create(['bundle_code' => 'AU-5GB-7D-TEST']);

        $this->get('/admin/provider-bundles')->assertOk()
            ->assertSee('Provider bundles')
            ->assertSeeInOrder(['AU-5GB-7D-TEST', 'Unavailable', 'Manual'])
            ->assertSeeInOrder(['esim_1GB_7D_JP_V2', 'eSIM, 1GB, 7 Days, Japan', 'JP', '1 GB', '7 days', '4G/5G', '1.23 USD', 'Available', 'Synced'])
            ->assertSee('2 of 2 bundles shown · 1 synced from eSIM Go');

        // Sidebar entry
        $this->get('/admin')->assertSee('Provider Bundles');
    }

    public function test_empty_state(): void
    {
        $this->get('/admin/provider-bundles')->assertOk()->assertSee('No provider bundles yet.');
        $this->synced();
        $this->get('/admin/provider-bundles?q=nothing-matches')->assertOk()->assertSee('No provider bundles match these filters.');
    }

    public function test_search_by_code_and_by_name(): void
    {
        $this->synced(['bundle_code' => 'esim_1GB_7D_JP_V2', 'description' => 'eSIM, 1GB, 7 Days, Japan']);
        $this->synced(['bundle_code' => 'esim_3GB_30D_FR_V2', 'description' => 'eSIM, 3GB, 30 Days, France']);

        $this->get('/admin/provider-bundles?q=JP_V2')->assertSee('esim_1GB_7D_JP_V2')->assertDontSee('esim_3GB_30D_FR_V2');
        $this->get('/admin/provider-bundles?q=France')->assertSee('esim_3GB_30D_FR_V2')->assertDontSee('esim_1GB_7D_JP_V2');
    }

    public function test_filters_country_data_validity_availability_source_and_plan(): void
    {
        $jp = $this->synced(['bundle_code' => 'B_JP', 'country_codes' => ['JP'], 'data_amount_mb' => 1000, 'duration_days' => 7]);
        $eu = $this->synced(['bundle_code' => 'B_EU', 'country_codes' => ['DE', 'FR'], 'is_unlimited' => true, 'data_amount_mb' => null, 'duration_days' => 30]);
        $big = $this->synced(['bundle_code' => 'B_BIG', 'country_codes' => ['US'], 'data_amount_mb' => 50000, 'duration_days' => 90]);
        $gone = $this->synced(['bundle_code' => 'B_GONE', 'country_codes' => ['JP'], 'is_available' => false]);
        $manual = ProviderBundleFactory::new()->create(['bundle_code' => 'B_MANUAL']);
        $this->draftPlan(bundle: ['bundle_code' => 'B_LINKED']);

        $codes = fn (string $query) => collect(['B_JP', 'B_EU', 'B_BIG', 'B_GONE', 'B_MANUAL', 'B_LINKED'])
            ->filter(fn ($code) => str_contains($this->get('/admin/provider-bundles?'.$query)->getContent(), '>'.$code.'<'))->values()->all();

        $this->assertSame(['B_JP', 'B_GONE'], $codes('country=jp'));
        $this->assertSame(['B_EU'], $codes('country=FR'));
        $this->assertSame(['B_EU'], $codes('data=unlimited'));
        $this->assertSame(['B_JP'], $codes('data=1to5&country=JP&availability=available'));
        $this->assertSame(['B_BIG'], $codes('data=over20'));
        $this->assertSame(['B_EU'], $codes('validity=16to30'));
        $this->assertSame(['B_BIG'], $codes('validity=over30'));
        $this->assertSame(['B_GONE'], $codes('availability=unavailable'));
        $this->assertSame(['B_MANUAL', 'B_LINKED'], $codes('source=manual'));
        $this->assertSame(['B_LINKED'], $codes('linked=linked'));
        $this->assertNotContains('B_LINKED', $codes('linked=unlinked'));
        $this->assertCount(6, $codes('data=nonsense&validity=bad&source=x'));   // unknown filter values are ignored
    }

    public function test_listing_is_paginated(): void
    {
        foreach (range(1, 27) as $i) {
            $this->synced(['bundle_code' => sprintf('esim_%02d', $i)]);
        }

        $this->get('/admin/provider-bundles')->assertOk()->assertSee('>esim_25<', false)->assertDontSee('>esim_26<', false);
        $this->get('/admin/provider-bundles?page=2')->assertOk()->assertSee('>esim_26<', false)->assertSee('>esim_27<', false)->assertDontSee('>esim_01<', false);
    }

    // ------------------------------------------------------------------ details

    public function test_bundle_details_page_shows_provider_information_and_the_linked_plan(): void
    {
        $this->country(['name' => 'Japan', 'iso2' => 'JP']);
        $bundle = $this->synced(['bundle_code' => 'esim_1GB_7D_JP_V2', 'description' => 'eSIM, 1GB, 7 Days, Japan', 'country_codes' => ['JP'],
            'data_amount_mb' => 1000, 'duration_days' => 7, 'speed' => '4G', 'provider_cost' => '1.2300', 'provider_currency' => 'USD',
            'raw_payload' => ['groups' => ['Standard Fixed'], 'billingType' => 'FixedCost']]);

        $this->get("/admin/provider-bundles/{$bundle->id}")->assertOk()
            ->assertSeeInOrder(['esim_1GB_7D_JP_V2', 'eSIM, 1GB, 7 Days, Japan', 'Japan', '1 GB', '7 days', '4G', '1.23 USD', 'Available', 'Synced from eSIM Go'])
            ->assertSee('Standard Fixed')->assertSee('FixedCost')
            ->assertSee('cannot be edited here')
            ->assertSee('Create plan from this bundle')
            ->assertDontSee('name="_method"', false)->assertDontSee('name="provider_cost"', false);   // read-only page

        $plan = $this->draftPlan(['name' => 'Japan 1GB'], bundle: ['bundle_code' => 'LINKED_ONE']);
        $this->get("/admin/provider-bundles/{$plan->provider_bundle_id}")->assertOk()
            ->assertSee('Japan 1GB')->assertDontSee('Create plan from this bundle');

        $gone = ProviderBundleFactory::new()->unavailable()->create();
        $this->get("/admin/provider-bundles/{$gone->id}")->assertOk()->assertSee('cannot be used for a new plan')->assertDontSee('Create plan from this bundle');

        $this->get('/admin/provider-bundles/999999')->assertNotFound();
    }

    // ------------------------------------------------------------------ access

    public function test_guests_and_customers_cannot_access_provider_bundle_pages(): void
    {
        $bundle = $this->synced();
        $plan = $this->draftPlan();
        $urls = ['/admin/provider-bundles', "/admin/provider-bundles/{$bundle->id}", '/admin/provider-bundles/search?q=x', "/admin/plans/{$plan->id}/provider-bundle"];

        auth()->logout();
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->put("/admin/plans/{$plan->id}/provider-bundle", ['provider_bundle_id' => $bundle->id])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());   // customer
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put("/admin/plans/{$plan->id}/provider-bundle", ['provider_bundle_id' => $bundle->id])->assertForbidden();
        $this->getJson('/admin/provider-bundles/search')->assertForbidden();

        $this->assertSame($plan->provider_bundle_id, $plan->fresh()->provider_bundle_id);
    }

    // ------------------------------------------------------------------ picker search (JSON)

    public function test_picker_search_only_returns_selectable_bundles(): void
    {
        $ok = $this->synced(['bundle_code' => 'esim_JP_OK', 'country_codes' => ['JP'], 'provider_cost' => '1.5000', 'provider_currency' => 'USD']);
        $this->synced(['bundle_code' => 'esim_JP_GONE', 'is_available' => false]);
        ProviderBundleFactory::new()->create(['bundle_code' => 'esim_JP_OTHER', 'provider' => 'other']);
        $linkedPlan = $this->draftPlan(bundle: ['bundle_code' => 'esim_JP_LINKED']);

        $json = $this->getJson('/admin/provider-bundles/search?q=esim_JP')->assertOk()->json('data');
        $this->assertSame(['esim_JP_OK'], array_column($json, 'code'));
        $this->assertSame(['id' => $ok->id, 'code' => 'esim_JP_OK', 'countries' => ['JP'], 'cost' => '1.5 USD', 'source' => 'Synced'],
            array_intersect_key($json[0], array_flip(['id', 'code', 'countries', 'cost', 'source'])));

        // When editing a plan, its own bundle is not "used by another plan".
        $this->assertContains('esim_JP_LINKED', array_column($this->getJson("/admin/provider-bundles/search?q=esim_JP&plan={$linkedPlan->id}")->json('data'), 'code'));
        $this->assertSame(['esim_JP_OK'], array_column($this->getJson('/admin/provider-bundles/search?country=jp')->json('data'), 'code'));
    }

    // ------------------------------------------------------------------ create plan from an existing bundle

    public function test_create_plan_from_a_bundle_preselects_it_and_prefills_plan_fields(): void
    {
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP']);
        $bundle = $this->synced(['bundle_code' => 'esim_UL_7D_JP_V2', 'description' => 'eSIM, Unlimited, 7 Days, Japan', 'country_codes' => ['JP'],
            'is_unlimited' => true, 'data_amount_mb' => null, 'duration_days' => 7, 'speed' => '5G']);

        $html = $this->get("/admin/plans/create?bundle={$bundle->id}")->assertOk()->getContent();

        $this->assertStringContainsString('name="provider_bundle_id" value="'.$bundle->id.'"', $html);
        $this->assertStringContainsString('esim_UL_7D_JP_V2', $html);
        $this->assertStringContainsString('eSIM, Unlimited, 7 Days, Japan', $html);
        $this->assertMatchesRegularExpression('/name="data_type" value="unlimited"\s+checked/', $html);
        $this->assertStringContainsString('name="validity_days" value="7"', $html);
        $this->assertMatchesRegularExpression('/value="'.$japan->id.'"\s+checked/', $html);
        $this->assertStringContainsString('data-bundle-picker', $html);
        $this->assertStringContainsString(route('admin.provider-bundles.search'), $html);
    }

    public function test_admin_creates_a_plan_with_an_existing_bundle_without_creating_another_bundle(): void
    {
        $bundle = $this->synced(['bundle_code' => 'esim_1GB_7D_JP_V2', 'provider_cost' => '1.2300', 'provider_currency' => 'USD']);

        $this->post('/admin/plans', $this->planForm(['provider_bundle_id' => $bundle->id]))->assertSessionHasNoErrors();

        $plan = Plan::sole();
        $this->assertSame([$bundle->id, false], [$plan->provider_bundle_id, $plan->is_active]);
        $this->assertSame(1, ProviderBundle::count());
        $this->assertSame(['esim_1GB_7D_JP_V2', '1.2300', 'USD'], [$bundle->fresh()->bundle_code, $bundle->fresh()->provider_cost, $bundle->fresh()->provider_currency]);
        $this->assertNotNull($bundle->fresh()->last_synced_at);

        // The bundle is now in use: it no longer appears in the picker and cannot be chosen again.
        $this->assertSame([], $this->getJson('/admin/provider-bundles/search')->json('data'));
        $this->post('/admin/plans', $this->planForm(['name' => 'Second', 'provider_bundle_id' => $bundle->id]))->assertSessionHasErrors('provider_bundle_id');
        $this->get("/admin/plans/create?bundle={$bundle->id}")->assertSee('This bundle is already used by the plan');
    }

    public function test_unavailable_bundles_cannot_be_selected_for_new_plans(): void
    {
        $gone = $this->synced(['is_available' => false]);

        $this->post('/admin/plans', $this->planForm(['provider_bundle_id' => $gone->id]))->assertSessionHasErrors('provider_bundle_id');
        $this->assertSame(0, Plan::count());
        $this->get("/admin/plans/create?bundle={$gone->id}")->assertSee('This bundle is not available from eSIM Go');
        $this->get('/admin/provider-bundles')->assertDontSee('Create plan</a>', false);
    }

    // ------------------------------------------------------------------ change provider bundle

    public function test_change_bundle_page_lists_only_other_selectable_bundles_with_confirmation(): void
    {
        $plan = $this->draftPlan(['name' => 'Japan plan'], bundle: ['bundle_code' => 'AU-5GB-7D-TEST']);
        $this->synced(['bundle_code' => 'esim_1GB_7D_JP_V2', 'country_codes' => ['JP']]);
        $this->synced(['bundle_code' => 'esim_GONE_JP', 'country_codes' => ['JP'], 'is_available' => false]);
        $this->draftPlan(bundle: ['bundle_code' => 'esim_USED_JP', 'country_codes' => ['JP']]);

        $html = $this->get("/admin/plans/{$plan->id}/provider-bundle?country=JP")->assertOk()
            ->assertSee('Current bundle')->assertSee('AU-5GB-7D-TEST')
            ->assertSee('esim_1GB_7D_JP_V2')->assertDontSee('esim_GONE_JP')->assertDontSee('esim_USED_JP')
            ->assertSee('Use this bundle')
            ->getContent();
        $this->assertStringContainsString('data-confirm="Change the provider bundle of “Japan plan” from AU-5GB-7D-TEST to esim_1GB_7D_JP_V2?', $html);

        $this->get("/admin/plans/{$plan->id}")->assertSee('Change provider bundle');
        $this->get("/admin/plans/{$plan->id}/edit")->assertSee('Change provider bundle');
    }

    public function test_relinking_changes_only_the_link_and_preserves_plan_prices_orders_and_old_bundle(): void
    {
        $country = $this->country(['iso2' => 'JP', 'name' => 'Japan']);
        $plan = $this->sellablePlan(['name' => 'Japan unlimited 7 days'], $country, '10.00', bundle: ['bundle_code' => 'AU-5GB-7D-TEST', 'provider_cost' => '4.5000']);
        PlanPriceFactory::new()->pending()->create(['plan_id' => $plan->id, 'effective_from' => now()->addDay()]);   // price history: a pending change
        $item = $this->orderFor($plan);
        $old = $plan->providerBundle;
        $new = $this->synced(['bundle_code' => 'esim_UL_7D_JP_V2', 'country_codes' => ['JP'], 'provider_cost' => '6.0000', 'provider_currency' => 'USD']);

        $planBefore = collect($plan->fresh()->toArray())->except(['provider_bundle_id', 'updated_at'])->all();
        $countriesBefore = $plan->countries()->pluck('countries.id')->all();
        $pricesBefore = PlanPrice::orderBy('id')->get()->toArray();
        $itemBefore = $item->fresh()->toArray();
        $oldBefore = $old->fresh()->toArray();
        $newBefore = $new->fresh()->toArray();

        $this->put("/admin/plans/{$plan->id}/provider-bundle", ['provider_bundle_id' => $new->id])
            ->assertRedirect("/admin/plans/{$plan->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'from AU-5GB-7D-TEST to esim_UL_7D_JP_V2'));

        $after = $plan->fresh();
        $this->assertSame([$plan->id, $new->id, true], [$after->id, $after->provider_bundle_id, $after->is_active]);   // same plan, still active
        $this->assertSame($planBefore, collect($after->toArray())->except(['provider_bundle_id', 'updated_at'])->all());
        $this->assertSame($countriesBefore, $after->countries()->pluck('countries.id')->all());
        $this->assertSame($pricesBefore, PlanPrice::orderBy('id')->get()->toArray());
        $this->assertSame($itemBefore, $item->fresh()->toArray());                    // historical snapshot unchanged
        $this->assertSame('AU-5GB-7D-TEST', $item->fresh()->bundle_code);
        $this->assertSame($oldBefore, $old->fresh()->toArray());                       // previous bundle kept, unchanged
        $this->assertSame($newBefore, $new->fresh()->toArray());                       // provider data untouched
        $this->assertSame(2, ProviderBundle::count());
    }

    public function test_relinking_warns_when_the_price_is_now_below_cost_and_never_activates(): void
    {
        $plan = $this->draftPlan();
        PlanPriceFactory::new()->manual('5.00')->create(['plan_id' => $plan->id, 'effective_from' => now()->subHour()]);
        $expensive = $this->synced(['provider_cost' => '8.0000', 'provider_currency' => 'USD']);

        $this->put("/admin/plans/{$plan->id}/provider-bundle", ['provider_bundle_id' => $expensive->id])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'below the new provider cost'));
        $this->assertFalse($plan->fresh()->is_active);   // inactive plans stay inactive
    }

    public function test_relinking_rejects_invalid_choices(): void
    {
        $plan = $this->draftPlan();
        $current = $plan->provider_bundle_id;
        $gone = $this->synced(['is_available' => false]);
        $used = $this->draftPlan()->provider_bundle_id;
        $other = ProviderBundleFactory::new()->create(['provider' => 'other']);

        $cases = [
            [[], 'Select a provider bundle.'],
            [['provider_bundle_id' => 'x'], 'Select a provider bundle.'],
            [['provider_bundle_id' => 999999], 'The selected provider bundle does not exist.'],
            [['provider_bundle_id' => $other->id], 'The selected provider bundle does not exist.'],
            [['provider_bundle_id' => $current], 'This plan already uses that provider bundle.'],
            [['provider_bundle_id' => $gone->id], 'That provider bundle is not available from eSIM Go and cannot be linked.'],
            [['provider_bundle_id' => $used], 'That provider bundle is already linked to another plan.'],
        ];
        foreach ($cases as [$data, $message]) {
            $this->from("/admin/plans/{$plan->id}/provider-bundle")
                ->put("/admin/plans/{$plan->id}/provider-bundle", $data)
                ->assertRedirect("/admin/plans/{$plan->id}/provider-bundle")
                ->assertSessionHasErrors(['provider_bundle_id' => $message]);
        }
        $this->assertSame($current, $plan->fresh()->provider_bundle_id);
    }

    // ------------------------------------------------------------------ the existing fake bundle

    public function test_the_existing_fake_bundle_is_never_replaced_automatically(): void
    {
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP']);
        $plan = $this->sellablePlan(['name' => 'Japan unlimited 7 days'], $japan, '19.99', bundle: ['bundle_code' => 'AU-5GB-7D-TEST', 'provider_cost' => '4.5000']);
        $fakeId = $plan->provider_bundle_id;

        // A catalogue sync that brings in real Japan bundles (mocked eSIM Go).
        $this->configureEsimGo(['esimgo.currency' => 'USD']);
        Http::fake([self::BASE.'/catalogue*' => Http::sequence()
            ->push([$this->bundle('esim_UL_7D_JP_V2', ['countries' => [['iso' => 'JP']], 'unlimited' => true])])->push([])]);
        app(CatalogueSynchroniser::class)->sync(fresh: true);

        // Admins browse, view and open the change page — nothing changes on its own.
        $this->get('/admin/provider-bundles?country=JP')->assertOk()->assertSee('esim_UL_7D_JP_V2');
        $this->get("/admin/plans/{$plan->id}")->assertOk();
        $this->get("/admin/plans/{$plan->id}/provider-bundle")->assertOk()->assertSee('esim_UL_7D_JP_V2');

        $this->assertSame($fakeId, $plan->fresh()->provider_bundle_id);
        $this->assertSame('AU-5GB-7D-TEST', $plan->fresh()->providerBundle->bundle_code);
        $this->assertTrue($plan->fresh()->is_active);
        $this->assertTrue(ProviderBundle::find($fakeId)->is_available);   // hand-entered bundles are never marked unavailable by a sync
    }
}
