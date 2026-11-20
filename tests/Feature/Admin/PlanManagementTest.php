<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\ProviderBundle;
use Database\Factories\PlanPriceFactory;
use Database\Factories\ProviderBundleFactory;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class PlanManagementTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
    }

    /**
     * Plan form data. Phase 9B-1: a new plan selects an existing provider bundle
     * (a fresh available one unless 'provider_bundle_id' is given). The cost /
     * currency / availability fields are only used when editing a manually
     * entered bundle; on create they are ignored.
     */
    private function valid(array $overrides = []): array
    {
        $overrides['countries'] ??= [$this->country()->id];
        if (! array_key_exists('provider_bundle_id', $overrides)) {
            $overrides['provider_bundle_id'] = ProviderBundleFactory::new()->create()->id;
        }

        return $overrides + [
            'name' => 'Japan 5 GB 7 days',
            'coverage_type' => 'local',
            'data_type' => 'fixed',
            'data_amount' => '5',
            'data_unit' => 'GB',
            'validity_days' => '7',
            'network_speed' => '5G',
            'features' => "Hotspot supported\nInstant activation\n\nHotspot supported",
            'badge' => 'popular',
            'provider_cost' => '4.25',
            'provider_currency' => 'usd',
            'bundle_available' => '1',
        ];
    }

    public function test_admin_creates_an_inactive_plan_with_its_provider_bundle_and_countries(): void
    {
        $country = $this->country();
        $bundle = ProviderBundleFactory::new()->create(['bundle_code' => 'esim_5GB_7D_JP_V2', 'provider_cost' => '4.2500', 'provider_currency' => 'USD']);

        $response = $this->post('/admin/plans', $this->valid(['countries' => [$country->id], 'provider_bundle_id' => $bundle->id]));

        $plan = Plan::with('providerBundle', 'countries')->firstOrFail();
        $response->assertRedirect("/admin/plans/{$plan->id}");
        $this->assertFalse($plan->is_active);
        $this->assertSame('japan-5-gb-7-days', $plan->slug);
        $this->assertSame(5000, $plan->data_amount_mb);   // 1 GB = 1,000 MB
        $this->assertSame(7, $plan->validity_days);
        $this->assertSame(['Hotspot supported', 'Instant activation'], $plan->features);
        $this->assertSame([$country->id], $plan->countries->pluck('id')->all());
        $this->assertSame(['esimgo', 'esim_5GB_7D_JP_V2', '4.2500', 'USD', true], [
            $plan->providerBundle->provider, $plan->providerBundle->bundle_code, $plan->providerBundle->provider_cost,
            $plan->providerBundle->provider_currency, $plan->providerBundle->is_available,
        ]);
        $this->assertSame($bundle->id, $plan->provider_bundle_id);   // the existing bundle is reused
        $this->assertSame(1, ProviderBundle::count());               // no new bundle was created
    }

    public function test_data_amount_units(): void
    {
        $this->post('/admin/plans', $this->valid(['data_amount' => '1.5', 'data_unit' => 'GB']))->assertSessionHasNoErrors();
        $this->assertSame(1500, Plan::latest('id')->first()->data_amount_mb);

        $this->post('/admin/plans', $this->valid(['name' => 'Plan B2', 'data_amount' => '500', 'data_unit' => 'MB']))->assertSessionHasNoErrors();
        $this->assertSame(500, Plan::latest('id')->first()->data_amount_mb);

        $this->post('/admin/plans', $this->valid(['name' => 'Plan B3', 'data_type' => 'unlimited', 'data_amount' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Plan::latest('id')->first()->data_amount_mb);

        $this->post('/admin/plans', $this->valid(['name' => 'Plan B4', 'data_amount' => '0.5', 'data_unit' => 'MB']))->assertSessionHasErrors('data_amount');
        $this->post('/admin/plans', $this->valid(['name' => 'Plan B5', 'data_amount' => '0']))->assertSessionHasErrors('data_amount');
        $this->post('/admin/plans', $this->valid(['name' => 'Plan B6', 'data_amount' => 'lots']))->assertSessionHasErrors('data_amount');
        $this->post('/admin/plans', $this->valid(['name' => 'Plan B7', 'data_amount' => '']))->assertSessionHasErrors('data_amount');
        $this->assertSame(3, Plan::count());
    }

    public function test_a_provider_bundle_must_be_selected_and_valid(): void
    {
        $other = ProviderBundleFactory::new()->create(['provider' => 'other']);
        $unavailable = ProviderBundleFactory::new()->unavailable()->create();

        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => null]))->assertSessionHasErrors(['provider_bundle_id' => 'Select a provider bundle.']);
        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => 'abc']))->assertSessionHasErrors('provider_bundle_id');
        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => 999999]))->assertSessionHasErrors(['provider_bundle_id' => 'The selected provider bundle does not exist.']);
        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => $other->id]))->assertSessionHasErrors('provider_bundle_id');
        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => $unavailable->id]))
            ->assertSessionHasErrors(['provider_bundle_id' => 'This provider bundle is not available from eSIM Go and cannot be used for a new plan.']);

        $this->assertDatabaseCount('plans', 0);
        $this->assertDatabaseCount('provider_bundles', 2);   // nothing created
    }

    public function test_a_bundle_already_used_by_a_plan_and_a_duplicate_slug_are_rejected(): void
    {
        $bundle = ProviderBundleFactory::new()->create();
        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => $bundle->id]))->assertSessionHasNoErrors();

        $this->post('/admin/plans', $this->valid(['name' => 'Another', 'provider_bundle_id' => $bundle->id]))
            ->assertSessionHasErrors(['provider_bundle_id' => 'This provider bundle is already linked to another plan.']);
        $this->post('/admin/plans', $this->valid())->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_cost_and_currency_posted_on_create_are_ignored_in_favour_of_the_bundle(): void
    {
        $bundle = ProviderBundleFactory::new()->create(['provider_cost' => '3.0000', 'provider_currency' => 'EUR', 'last_synced_at' => now()]);

        $this->post('/admin/plans', $this->valid(['provider_bundle_id' => $bundle->id, 'provider_cost' => '0.01', 'provider_currency' => 'USD', 'bundle_code' => 'HACK']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['3.0000', 'EUR', $bundle->bundle_code], [$bundle->fresh()->provider_cost, $bundle->fresh()->provider_currency, $bundle->fresh()->bundle_code]);
        $this->assertSame(1, ProviderBundle::count());
    }

    public function test_country_coverage_rules(): void
    {
        [$a, $b] = [$this->country(), $this->country()];

        $this->post('/admin/plans', $this->valid(['countries' => [$a->id, $b->id]]))
            ->assertSessionHasErrors(['countries' => 'A local plan must cover exactly one country.']);
        $this->post('/admin/plans', $this->valid(['coverage_type' => 'regional', 'countries' => [$a->id]]))
            ->assertSessionHasErrors(['countries' => 'A regional plan must cover at least two countries.']);
        $this->post('/admin/plans', $this->valid(['countries' => []]))->assertSessionHasErrors('countries');
        $this->post('/admin/plans', $this->valid(['coverage_type' => 'regional', 'countries' => [$a->id, $a->id]]))->assertSessionHasErrors('countries.0');
        $this->post('/admin/plans', $this->valid(['countries' => [999999]]))->assertSessionHasErrors('countries.0');
        $this->assertDatabaseCount('plans', 0);

        $this->post('/admin/plans', $this->valid(['coverage_type' => 'regional', 'region_name' => 'Test region', 'countries' => [$a->id, $b->id]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Plan::firstOrFail()->countries()->count());
    }

    public function test_inactive_countries_cannot_be_added_but_an_existing_link_may_stay(): void
    {
        $inactive = $this->country(['is_active' => false]);
        $this->post('/admin/plans', $this->valid(['countries' => [$inactive->id]]))->assertSessionHasErrors('countries');

        $country = $this->country();
        $plan = $this->draftPlan(country: $country);
        $country->update(['is_active' => false]);   // deactivated after being linked

        $this->put("/admin/plans/{$plan->id}", $this->valid(['countries' => [$country->id], 'provider_bundle_id' => null, 'name' => $plan->name, 'slug' => $plan->slug]))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_edit_a_plan_and_its_manually_entered_bundle(): void
    {
        $plan = $this->draftPlan();   // factory bundle = entered manually (never synced)
        $code = $plan->providerBundle->bundle_code;

        $this->put("/admin/plans/{$plan->id}", $this->valid(['provider_bundle_id' => null, 'bundle_code' => 'CHANGED', 'name' => 'Renamed', 'slug' => 'renamed', 'provider_cost' => '6', 'validity_days' => '30', 'countries' => [$plan->countries->first()->id]]))
            ->assertRedirect("/admin/plans/{$plan->id}")
            ->assertSessionHasNoErrors();

        $plan->refresh()->load('providerBundle');
        $this->assertSame(['Renamed', 'renamed', 30, '6.0000'], [$plan->name, $plan->slug, $plan->validity_days, $plan->providerBundle->provider_cost]);
        $this->assertSame($code, $plan->providerBundle->bundle_code);   // the code is changed only by re-linking
        $this->assertSame(1, ProviderBundle::count());   // same bundle updated, not duplicated
    }

    public function test_editing_a_plan_never_changes_a_synced_bundle(): void
    {
        $plan = $this->draftPlan(bundle: ['provider_cost' => '2.0000', 'provider_currency' => 'USD', 'is_available' => true, 'last_synced_at' => now()]);
        $before = $plan->providerBundle->fresh()->toArray();

        $this->put("/admin/plans/{$plan->id}", $this->valid(['provider_bundle_id' => 999, 'name' => 'Renamed', 'provider_cost' => '0.01', 'provider_currency' => 'GBP', 'bundle_available' => '0', 'countries' => [$plan->countries->first()->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $plan->fresh()->name);
        $this->assertSame($before, $plan->providerBundle->fresh()->toArray());
        $this->assertSame($before['id'], $plan->fresh()->provider_bundle_id);   // the link is not changed by the edit form

        $this->get("/admin/plans/{$plan->id}/edit")->assertOk()
            ->assertSee('Change provider bundle')
            ->assertDontSee('name="provider_cost"', false)
            ->assertSee('read-only');
    }

    public function test_activation_requires_the_plan_to_be_sellable(): void
    {
        $plan = $this->draftPlan();

        $this->post("/admin/plans/{$plan->id}/activate")->assertSessionHasErrors('plan');
        $this->assertFalse($plan->fresh()->is_active);

        PlanPriceFactory::new()->pending()->create(['plan_id' => $plan->id]);
        $this->post("/admin/plans/{$plan->id}/activate")->assertSessionHasErrors('plan');   // pending price is not enough

        PlanPriceFactory::new()->create(['plan_id' => $plan->id, 'effective_from' => now()->subHour()]);
        $plan->providerBundle->update(['is_available' => false]);
        $this->post("/admin/plans/{$plan->id}/activate")->assertSessionHasErrors('plan');

        $plan->providerBundle->update(['is_available' => true]);
        $country = $plan->countries->first();
        $country->update(['is_active' => false]);
        $this->post("/admin/plans/{$plan->id}/activate")->assertSessionHasErrors('plan');

        $country->update(['is_active' => true]);
        $this->post("/admin/plans/{$plan->id}/activate")->assertSessionHasNoErrors();
        $this->assertTrue($plan->fresh()->is_active);

        $this->post("/admin/plans/{$plan->id}/deactivate");
        $this->assertFalse($plan->fresh()->is_active);
    }

    public function test_plans_can_never_be_deleted(): void
    {
        $plan = $this->sellablePlan();

        $this->delete("/admin/plans/{$plan->id}")->assertStatus(405);
        $this->assertModelExists($plan);
    }

    public function test_plan_list_filters(): void
    {
        $japan = $this->country(['name' => 'Japan', 'slug' => 'japan']);
        $this->sellablePlan(['name' => 'Japan Unlimited', 'data_type' => 'unlimited', 'data_amount_mb' => null], $japan);
        $this->draftPlan(['name' => 'France Fixed']);

        $this->get('/admin/plans')->assertOk()->assertSee('Japan Unlimited')->assertSee('France Fixed');
        $this->get('/admin/plans?country='.$japan->id)->assertSee('Japan Unlimited')->assertDontSee('France Fixed');
        $this->get('/admin/plans?data_type=fixed')->assertSee('France Fixed')->assertDontSee('Japan Unlimited');
        $this->get('/admin/plans?status=inactive')->assertSee('France Fixed')->assertDontSee('Japan Unlimited');
        $this->get('/admin/plans?q=unlimited')->assertSee('Japan Unlimited')->assertDontSee('France Fixed');
    }
}
