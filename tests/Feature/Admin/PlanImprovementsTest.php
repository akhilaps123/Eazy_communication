<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Database\Factories\PlanPriceFactory;
use Database\Factories\ProviderBundleFactory;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

/** Phase 6: simplified plan form, bundle correction, list, pricing UX and Reject. */
class PlanImprovementsTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = $this->admin();
        $this->actingAs($this->adminUser);
    }

    /** What the simplified form submits (Advanced fields are always part of the form). */
    /** Phase 9B-1: a new plan selects an existing provider bundle (a fresh 4.50 USD one by default). */
    private function form(array $overrides = []): array
    {
        $overrides['countries'] ??= [$this->country()->id];
        $overrides['provider_bundle_id'] ??= ProviderBundleFactory::new()->cost('4.5000', 'USD')->create()->id;

        return $overrides + [
            'name' => 'Japan 5GB', 'data_type' => 'fixed', 'data_amount' => '5', 'data_unit' => 'GB',
            'validity_days' => '7', 'network_speed' => '5G', 'badge' => '',
            'coverage_type' => 'local', 'region_name' => '',
            'slug' => '', 'display_title' => '', 'description' => '', 'features' => '', 'sort_order' => '',
        ];
    }

    private function editForm(Plan $plan, array $overrides = []): array
    {
        $plan->load('providerBundle', 'countries');

        return $overrides + [
            'name' => $plan->name, 'data_type' => $plan->data_type,
            'data_amount' => $plan->data_amount_mb ? (string) ($plan->data_amount_mb / 1000) : '', 'data_unit' => 'GB',
            'validity_days' => (string) $plan->validity_days, 'network_speed' => (string) $plan->network_speed,
            'badge' => (string) $plan->badge, 'coverage_type' => $plan->coverage_type, 'region_name' => (string) $plan->region_name,
            'countries' => $plan->countries->pluck('id')->all(),
            'bundle_code' => $plan->providerBundle->bundle_code, 'provider_cost' => (string) $plan->providerBundle->provider_cost,
            'provider_currency' => $plan->providerBundle->provider_currency, 'bundle_available' => '1',
            'slug' => $plan->slug, 'display_title' => (string) $plan->display_title, 'description' => (string) $plan->description,
            'features' => implode("\n", (array) $plan->features), 'sort_order' => (string) $plan->sort_order,
        ];
    }

    // ------------------------------------------------------------ form layout

    public function test_form_has_four_sections_with_a_collapsible_advanced_section(): void
    {
        $html = $this->get('/admin/plans/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/A<\/span> Plan information.*B<\/span> Coverage.*C<\/span> Provider bundle.*<details class="adm-form-section adm-advanced"\s*>.*D<\/span> Advanced settings/s', $html);
        // Advanced holds exactly these fields, all inside the <details> element.
        $advanced = substr($html, strpos($html, '<details'));
        foreach (['slug', 'display_title', 'description', 'features', 'sort_order'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $advanced);
        }
        $this->assertStringContainsString('data-show-when="data_type=fixed"', $html);
        $this->assertStringContainsString('data-show-when="coverage_type=regional,global"', $html);
        $this->assertStringContainsString('<datalist id="network-speed-options">', $html);
        $this->assertStringContainsString('data-country-search', $html);
        $this->assertStringContainsString('0 selected', $html);
        $this->assertStringContainsString('js/admin.js', $html);
    }

    public function test_advanced_section_opens_when_one_of_its_fields_has_an_error(): void
    {
        $this->assertMatchesRegularExpression('/<details class="adm-form-section adm-advanced"\s*>/', $this->get('/admin/plans/create')->getContent());   // closed by default

        // Like a browser: submit from the create page and follow the redirect back to it.
        $html = $this->followingRedirects()->from('/admin/plans/create')
            ->post('/admin/plans', $this->form(['slug' => 'Bad Slug!']))->getContent();

        $this->assertMatchesRegularExpression('/<details class="adm-form-section adm-advanced"\s+open\s*>/', $html);
        $this->assertStringContainsString('value="Bad Slug!"', $html);   // the admin's typed value is kept for correction
    }

    // ------------------------------------------------------------- create/edit

    public function test_plan_can_be_created_with_the_simplified_form_using_only_main_fields(): void
    {
        $country = $this->country();
        $this->post('/admin/plans', $this->form(['countries' => [$country->id]]))->assertSessionHasNoErrors();

        $plan = Plan::with('providerBundle')->firstOrFail();
        $this->assertSame(['japan-5gb', 5000, 7, 'USD', '4.5000', false], [
            $plan->slug, $plan->data_amount_mb, $plan->validity_days, $plan->providerBundle->provider_currency,
            $plan->providerBundle->provider_cost, $plan->is_active,
        ]);
        $this->assertNull($plan->display_title);
        $this->assertNull($plan->features);
    }

    public function test_editing_keeps_all_advanced_values(): void
    {
        $this->post('/admin/plans', $this->form([
            'slug' => 'custom-slug', 'display_title' => 'Custom title', 'description' => 'Long text',
            'features' => "Hotspot\nInstant activation", 'sort_order' => '4',
        ]))->assertSessionHasNoErrors();
        $plan = Plan::firstOrFail();

        // Edit a main field only; the Advanced values come back unchanged from the form.
        $this->put("/admin/plans/{$plan->id}", $this->editForm($plan, ['validity_days' => '10']))->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(['custom-slug', 'Custom title', 'Long text', ['Hotspot', 'Instant activation'], 4, 10], [
            $plan->slug, $plan->display_title, $plan->description, $plan->features, $plan->sort_order, $plan->validity_days,
        ]);

        // The edit page pre-fills every Advanced field.
        $this->get("/admin/plans/{$plan->id}/edit")
            ->assertSee('value="custom-slug"', false)->assertSee('value="Custom title"', false)
            ->assertSee('Long text')->assertSee("Hotspot\nInstant activation", false)->assertSee('name="sort_order" value="4"', false);
    }

    public function test_fixed_and_unlimited_data(): void
    {
        $this->post('/admin/plans', $this->form(['data_amount' => '1.5']))->assertSessionHasNoErrors();
        $this->assertSame(1500, Plan::latest('id')->first()->data_amount_mb);

        // Unlimited: a leftover (hidden) amount is ignored.
        $this->post('/admin/plans', $this->form(['name' => 'Unl', 'data_type' => 'unlimited', 'data_amount' => '5']))->assertSessionHasNoErrors();
        $unlimited = Plan::latest('id')->first();
        $this->assertSame(['unlimited', null], [$unlimited->data_type, $unlimited->data_amount_mb]);
        $this->assertMatchesRegularExpression('/name="data_type" value="unlimited"\s+checked/', $this->get("/admin/plans/{$unlimited->id}/edit")->getContent());

        $this->post('/admin/plans', $this->form(['name' => 'No amount', 'data_amount' => '']))->assertSessionHasErrors('data_amount');
    }

    public function test_country_picker_preserves_links_and_labels_inactive_countries(): void
    {
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP']);
        $korea = $this->country(['name' => 'Korea', 'iso2' => 'KR']);
        $this->post('/admin/plans', $this->form(['coverage_type' => 'regional', 'countries' => [$japan->id, $korea->id]]))->assertSessionHasNoErrors();
        $plan = Plan::firstOrFail();
        $korea->update(['is_active' => false]);

        $this->get("/admin/plans/{$plan->id}/edit")
            ->assertSee('2 selected')
            ->assertSee('data-country-name="korea kr"', false)
            ->assertSee('class="adm-check adm-country-option is-selected"', false)
            ->assertSeeInOrder(['Korea', 'inactive']);

        $this->put("/admin/plans/{$plan->id}", $this->editForm($plan))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$japan->id, $korea->id], $plan->countries()->pluck('countries.id')->all());

        // A new plan cannot add the inactive country.
        $this->post('/admin/plans', $this->form(['name' => 'Other', 'countries' => [$korea->id]]))->assertSessionHasErrors('countries');
    }

    // -------------------------------------------------------- provider currency

    public function test_provider_currency_comes_from_the_bundle_on_create_and_from_the_list_when_editing_a_manual_bundle(): void
    {
        // Create: no cost / currency inputs — they belong to the selected bundle.
        $this->get('/admin/plans/create')->assertOk()
            ->assertDontSee('name="provider_currency"', false)->assertDontSee('name="provider_cost"', false)
            ->assertSee('Select provider bundle');
        $eur = ProviderBundleFactory::new()->cost('3.0000', 'EUR')->create();
        $this->post('/admin/plans', $this->form(['provider_bundle_id' => $eur->id, 'provider_currency' => 'USD']))->assertSessionHasNoErrors();
        $this->assertSame('EUR', Plan::firstOrFail()->providerBundle->provider_currency);

        // Edit (manually entered bundle): currency must come from the dropdown list.
        $plan = $this->draftPlan();
        $this->assertMatchesRegularExpression('/<option value="USD"\s+selected>USD<\/option>/', $this->get("/admin/plans/{$plan->id}/edit")->getContent());
        $this->get("/admin/plans/{$plan->id}/edit")->assertSee('<option value="EUR"', false)->assertSee('<option value="GBP"', false);

        foreach (['USD', 'EUR', 'GBP', 'gbp'] as $currency) {
            $this->put("/admin/plans/{$plan->id}", $this->editForm($plan, ['provider_currency' => $currency]))->assertSessionHasNoErrors();
        }
        $this->assertSame('GBP', $plan->providerBundle->fresh()->provider_currency);
        foreach (['', 'UDS', 'JPY', 'US'] as $currency) {
            $this->put("/admin/plans/{$plan->id}", $this->editForm($plan, ['provider_currency' => $currency]))->assertSessionHasErrors('provider_currency');
        }
        $this->assertSame('GBP', $plan->providerBundle->fresh()->provider_currency);
    }

    public function test_an_existing_currency_outside_the_list_is_kept_when_editing(): void
    {
        $plan = $this->draftPlan(bundle: ['provider_currency' => 'JPY', 'provider_cost' => '500.0000']);

        $this->assertMatchesRegularExpression('/<option value="JPY"\s+selected>JPY \(current\)<\/option>/', $this->get("/admin/plans/{$plan->id}/edit")->getContent());
        $this->put("/admin/plans/{$plan->id}", $this->editForm($plan))->assertSessionHasNoErrors();
        $this->assertSame('JPY', $plan->providerBundle->fresh()->provider_currency);

        // Other manually entered bundles still cannot choose it.
        $other = $this->draftPlan();
        $this->put("/admin/plans/{$other->id}", $this->editForm($other, ['provider_currency' => 'JPY']))->assertSessionHasErrors('provider_currency');
    }

    // --------------------------------------------------------- bundle columns

    public function test_plan_values_are_not_copied_into_the_provider_bundle(): void
    {
        // Provider catalogue data already stored (e.g. by a sync) is never overwritten by creating or editing a plan.
        $bundle = ProviderBundleFactory::new()->cost('4.5000', 'USD')
            ->create(['data_amount_mb' => 5120, 'is_unlimited' => false, 'duration_days' => 7, 'country_codes' => ['JP'], 'speed' => '4G']);
        $this->post('/admin/plans', $this->form(['provider_bundle_id' => $bundle->id, 'data_amount' => '10', 'validity_days' => '14']))->assertSessionHasNoErrors();
        $bundle->refresh();
        $this->assertSame([5120, false, 7, ['JP'], '4G'], [$bundle->data_amount_mb, $bundle->is_unlimited, $bundle->duration_days, $bundle->country_codes, $bundle->speed]);

        $plan = Plan::firstOrFail();
        $this->put("/admin/plans/{$plan->id}", $this->editForm($plan, ['data_amount' => '20', 'validity_days' => '30', 'network_speed' => '5G']))->assertSessionHasNoErrors();

        $bundle->refresh();
        $this->assertSame([5120, false, 7, ['JP'], '4G'], [$bundle->data_amount_mb, $bundle->is_unlimited, $bundle->duration_days, $bundle->country_codes, $bundle->speed]);
        $this->assertSame([20000, 30], [$plan->fresh()->data_amount_mb, $plan->fresh()->validity_days]);
        $this->assertSame('4.5000', $bundle->provider_cost);   // code, cost, currency still saved
    }

    // ------------------------------------------------------------------- list

    public function test_list_shows_pending_counts_actions_and_provider_details(): void
    {
        $plan = $this->sellablePlan(['name' => 'Listed plan'], bundle: ['bundle_code' => 'LIST_CODE', 'provider_cost' => '3.2500']);
        PlanPriceFactory::new()->pending()->create(['plan_id' => $plan->id, 'effective_from' => now()->subHours(2)]);
        PlanPriceFactory::new()->pending()->create(['plan_id' => $plan->id, 'effective_from' => now()->subHours(3)]);
        PlanPriceFactory::new()->create(['plan_id' => $plan->id, 'approval_status' => 'rejected', 'is_active' => false, 'effective_from' => now()->subHours(4)]);

        $this->get('/admin/plans')->assertOk()
            ->assertSee('2 pending')                              // rejected prices are not counted
            ->assertSee('$10.00')
            ->assertSee('LIST_CODE')->assertSee('Cost $3.25')
            ->assertSee('Active · on sale')
            ->assertSee('href="'.route('admin.plans.edit', $plan).'"', false)
            ->assertSee('Local coverage');
    }

    public function test_active_but_unsellable_plans_explain_why(): void
    {
        $this->sellablePlan(['name' => 'No price plan'], priceState: ['approval_status' => 'pending', 'is_active' => false]);
        $this->sellablePlan(['name' => 'Bundle gone'], bundle: ['is_available' => false]);
        $this->sellablePlan(['name' => 'Country off'], $this->country(['is_active' => false]));
        $below = $this->sellablePlan(['name' => 'Cost rose'], price: '10.00');
        $below->providerBundle->update(['provider_cost' => '11.0000']);
        $this->draftPlan(['name' => 'Draft plan']);

        $this->get('/admin/plans')->assertOk()
            ->assertSee('No approved current price')
            ->assertSee('Bundle not available from eSIM Go')
            ->assertSee('No active country')
            ->assertSee('Current price is below provider cost')
            ->assertSee('Active · hidden');

        // The list agrees with the real catalogue rules: none of these is sellable.
        $this->assertCount(0, app(\App\Services\CatalogueService::class)->sellablePlans());
    }

    // ---------------------------------------------------------------- pricing

    public function test_price_form_only_shows_the_relevant_amount_field_and_preview_shows_margin(): void
    {
        $plan = $this->draftPlan(bundle: ['provider_cost' => '10.0000', 'provider_currency' => 'USD']);

        $this->get("/admin/plans/{$plan->id}")
            ->assertSee('data-show-when="pricing_method=manual"', false)
            ->assertSee('data-show-when="pricing_method=markup_percent,markup_fixed"', false)
            ->assertSee('Markup percentage (%)')->assertSee('Fixed markup amount (USD)');

        $this->from("/admin/plans/{$plan->id}")->post("/admin/plans/{$plan->id}/prices", [
            'pricing_method' => 'manual', 'manual_price' => '11.49', 'effective_from' => now()->format('Y-m-d\TH:i'), 'intent' => 'preview',
        ])->assertSessionHas('price_preview', fn ($p) => $p['margin'] === ['cost' => '$10.00', 'price' => '$11.49', 'margin' => '$1.49', 'percent' => '13']);

        $this->get("/admin/plans/{$plan->id}")->assertSee('Customers would pay')->assertSee('$1.49 (13%)');
        $this->assertDatabaseCount('plan_prices', 0);
    }

    public function test_markup_preview_and_no_margin_for_other_currencies(): void
    {
        $plan = $this->draftPlan(bundle: ['provider_cost' => '20.0000', 'provider_currency' => 'USD']);
        $this->from("/admin/plans/{$plan->id}")->post("/admin/plans/{$plan->id}/prices", [
            'pricing_method' => 'markup_percent', 'markup_value' => '25', 'manual_price' => '999', 'effective_from' => now()->format('Y-m-d\TH:i'), 'intent' => 'preview',
        ])->assertSessionHas('price_preview', fn ($p) => $p['formatted'] === '$25.00' && $p['margin']['margin'] === '$5.00' && $p['margin']['percent'] === '20');

        $eur = $this->draftPlan(bundle: ['provider_cost' => '20.0000', 'provider_currency' => 'EUR']);
        $this->from("/admin/plans/{$eur->id}")->post("/admin/plans/{$eur->id}/prices", [
            'pricing_method' => 'manual', 'manual_price' => '30', 'effective_from' => now()->format('Y-m-d\TH:i'), 'intent' => 'preview',
        ])->assertSessionHas('price_preview', fn ($p) => $p['margin'] === null);
    }

    // ------------------------------------------------------------------ reject

    public function test_admin_can_reject_a_pending_price_and_it_stays_in_history(): void
    {
        $plan = $this->draftPlan();
        $price = PlanPriceFactory::new()->pending()->manual('9.99')->create(['plan_id' => $plan->id]);

        $this->post("/admin/plans/{$plan->id}/prices/{$price->id}/reject")->assertSessionHasNoErrors();

        $price->refresh();
        $this->assertSame(['rejected', false, $this->adminUser->id, '9.99'], [$price->approval_status, $price->is_active, $price->approved_by, $price->calculated_price]);
        $this->assertSame(1, PlanPrice::count());   // never deleted
        $this->get("/admin/plans/{$plan->id}")->assertSee('Rejected')->assertDontSee('Waiting for approval');
        $this->get('/admin')->assertSee('Nothing to review right now');
    }

    public function test_only_pending_prices_can_be_rejected(): void
    {
        $plan = $this->draftPlan();
        $approved = PlanPriceFactory::new()->create(['plan_id' => $plan->id]);
        $rejected = PlanPriceFactory::new()->create(['plan_id' => $plan->id, 'approval_status' => 'rejected', 'is_active' => false, 'effective_from' => now()->subDays(2)]);
        $ended = PlanPriceFactory::new()->create(['plan_id' => $plan->id, 'effective_from' => now()->subDays(5), 'effective_to' => now()->subDays(3)]);
        $deactivated = PlanPriceFactory::new()->create(['plan_id' => $plan->id, 'is_active' => false, 'effective_from' => now()->subDays(6)]);

        foreach ([$approved, $rejected, $ended, $deactivated] as $price) {
            $before = $price->fresh()->approval_status;
            $this->post("/admin/plans/{$plan->id}/prices/{$price->id}/reject")->assertSessionHasErrors(['price' => 'Only pending prices can be rejected.']);
            $this->assertSame($before, $price->fresh()->approval_status);
        }

        // A rejected price cannot be approved or edited afterwards either.
        $this->post("/admin/plans/{$plan->id}/prices/{$rejected->id}/approve")->assertSessionHasErrors('price');
        $this->get("/admin/plans/{$plan->id}/prices/{$rejected->id}/edit")->assertRedirect("/admin/plans/{$plan->id}");
    }

    public function test_customers_cannot_reject_and_reject_is_scoped_to_its_plan(): void
    {
        $plan = $this->draftPlan();
        $price = PlanPriceFactory::new()->pending()->create(['plan_id' => $plan->id]);
        $other = $this->draftPlan();

        $this->post("/admin/plans/{$other->id}/prices/{$price->id}/reject")->assertNotFound();

        $this->actingAs(User::factory()->create());
        $this->post("/admin/plans/{$plan->id}/prices/{$price->id}/reject")->assertForbidden();
        $this->assertSame('pending', $price->fresh()->approval_status);
    }

    public function test_rejected_prices_never_reach_the_customer_catalogue(): void
    {
        $country = $this->country(['slug' => 'testland']);
        $plan = $this->sellablePlan(['slug' => 'rej-plan', 'is_active' => true], $country, priceState: ['approval_status' => 'rejected', 'is_active' => true]);

        $this->assertNull($plan->currentPrice('USD'));
        $this->getJson('/api/plans/rej-plan')->assertNotFound();
        $this->getJson('/api/countries')->assertJsonCount(0, 'data');
        $this->enableLiveCatalogue();
        $this->get('/esim/testland')->assertNotFound();
    }

    // ---------------------------------------------------------------- history

    public function test_history_labels_in_effect_superseded_scheduled_pending_rejected_ended_deactivated(): void
    {
        $plan = $this->sellablePlan(price: '10.00', priceState: ['effective_from' => now()->subDays(10)]);   // superseded
        PlanPriceFactory::new()->manual('11.00')->create(['plan_id' => $plan->id, 'effective_from' => now()->subDay()]);   // in effect
        PlanPriceFactory::new()->manual('12.00')->create(['plan_id' => $plan->id, 'effective_from' => now()->addDays(3)]);   // scheduled
        PlanPriceFactory::new()->pending()->manual('13.00')->create(['plan_id' => $plan->id, 'effective_from' => now()->subHours(5)]);
        PlanPriceFactory::new()->manual('14.00')->create(['plan_id' => $plan->id, 'approval_status' => 'rejected', 'is_active' => false, 'effective_from' => now()->subHours(6)]);
        PlanPriceFactory::new()->manual('15.00')->create(['plan_id' => $plan->id, 'effective_from' => now()->subDays(20), 'effective_to' => now()->subDays(15)]);
        PlanPriceFactory::new()->manual('16.00')->create(['plan_id' => $plan->id, 'is_active' => false, 'effective_from' => now()->subDays(25)]);

        $html = preg_replace('/\s+/', ' ', strip_tags(substr($c = $this->get("/admin/plans/{$plan->id}")->getContent(), strpos($c, 'Price history'))));
        foreach (['$10.00' => 'Superseded', '$11.00' => 'In effect', '$12.00' => 'Scheduled', '$13.00' => 'Pending', '$14.00' => 'Rejected', '$15.00' => 'Ended', '$16.00' => 'Deactivated'] as $amount => $label) {
            $this->assertMatchesRegularExpression('/'.preg_quote($amount, '/').'[^$]*'.$label.'/', $html, "$amount should be $label");
        }
    }

    public function test_warning_when_an_approved_markup_price_used_an_older_provider_cost(): void
    {
        $plan = $this->draftPlan(bundle: ['provider_cost' => '20.0000', 'provider_currency' => 'USD']);
        PlanPriceFactory::new()->create([
            'plan_id' => $plan->id, 'pricing_method' => 'markup_percent', 'markup_value' => '25',
            'manual_price' => null, 'calculated_price' => '25.00',
        ]);

        $this->get("/admin/plans/{$plan->id}")->assertDontSee('data-stale-price', false);

        $plan->providerBundle->update(['provider_cost' => '22.0000']);
        $this->get("/admin/plans/{$plan->id}")
            ->assertSee('data-stale-price', false)
            ->assertSee('Calculated from an older provider cost')
            ->assertSee('$27.50');
    }

    // --------------------------------------------------------- pending section

    public function test_waiting_for_approval_section_near_the_top(): void
    {
        $plan = $this->draftPlan(bundle: ['provider_cost' => '10.0000', 'provider_currency' => 'USD']);
        $price = PlanPriceFactory::new()->pending()->manual('11.49')->create(['plan_id' => $plan->id, 'created_by' => $this->adminUser->id]);

        $html = $this->get("/admin/plans/{$plan->id}")->assertOk()->getContent();

        $this->assertTrue(strpos($html, 'Waiting for approval') < strpos($html, 'Price history'));
        $section = substr($html, strpos($html, 'id="pending-prices"'), strpos($html, 'Price history') - strpos($html, 'id="pending-prices"'));
        $this->assertStringContainsString('$11.49', $section);
        $this->assertStringContainsString('Manual price', $section);
        $this->assertStringContainsString('$1.49 (13%)', $section);
        $this->assertStringContainsString($this->adminUser->name, $section);
        $this->assertStringContainsString(route('admin.plans.prices.approve', [$plan, $price]), $section);
        $this->assertStringContainsString(route('admin.plans.prices.reject', [$plan, $price]), $section);

        // Approval rules unchanged: approving still works from the section.
        $this->post(route('admin.plans.prices.approve', [$plan, $price]))->assertSessionHasNoErrors();
        $this->assertSame('approved', $price->fresh()->approval_status);
    }

    public function test_margin_and_provider_cost_never_reach_customers(): void
    {
        $country = $this->country(['slug' => 'testland']);
        $this->sellablePlan(['slug' => 'secret-cost'], $country, '11.49', bundle: ['provider_cost' => '10.0000', 'bundle_code' => 'SECRET_CODE']);

        $this->enableLiveCatalogue();
        foreach (['/esim/testland', '/', '/new-esim'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('10.00')->assertDontSee('$1.49')->assertDontSee('Margin')->assertDontSee('SECRET_CODE');
        }
        $json = $this->getJson('/api/plans/secret-cost')->assertOk()->getContent();
        $this->assertStringNotContainsStringIgnoringCase('margin', $json);
        $this->assertStringNotContainsString('10.00', $json);
    }
}
