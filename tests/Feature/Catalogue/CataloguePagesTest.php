<?php

namespace Tests\Feature\Catalogue;

use App\Models\Plan;
use Database\Factories\PlanPriceFactory;
use Tests\Feature\Database\DatabaseTestCase;

class CataloguePagesTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private function japanWithPlans(): array
    {
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'iso3' => 'JPN', 'slug' => 'japan', 'is_popular' => true]);
        $fixed = $this->sellablePlan(['slug' => 'jp-5gb', 'data_amount_mb' => 5000, 'validity_days' => 7], $japan, '12.99', ['compare_at_price' => '18.99'],
            ['bundle_code' => 'SECRET_BUNDLE_JP', 'provider_cost' => '7.1234']);
        $unlimited = $this->sellablePlan(['slug' => 'jp-unl', 'data_type' => 'unlimited', 'data_amount_mb' => null, 'validity_days' => 10], $japan, '44.99');

        return [$japan, $fixed, $unlimited];
    }

    // ------------------------------------------------------- live catalogue OFF

    public function test_live_catalogue_is_off_by_default(): void
    {
        $this->assertFalse(config('catalogue.live'));
        $this->assertSame('USD', config('catalogue.currency'));
    }

    public function test_with_live_off_the_existing_static_pages_are_unchanged_even_when_data_exists(): void
    {
        $this->japanWithPlans();
        $this->country(['name' => 'Databaseland', 'slug' => 'databaseland']);

        $this->get('/')->assertOk()->assertSee('$34.99')->assertSee('RoamVault')->assertDontSee('Databaseland')->assertDontSee('/esim/japan');
        $this->get('/new-esim')->assertOk()->assertSee('Showing 1-12 of 256 plans')->assertDontSee('data-plans=', false);
        $this->get('/japan-esim')->assertOk()->assertSee('Japan eSIM')->assertDontSee('EAZY_PLANS');
        $this->get('/esim/japan')->assertNotFound();
    }

    // -------------------------------------------------------- live catalogue ON

    public function test_home_shows_destinations_and_offer_cards_from_the_database(): void
    {
        $this->enableLiveCatalogue();
        $this->japanWithPlans();

        $this->get('/')->assertOk()
            ->assertSee('href="'.url('/esim/japan').'" class="destination-item"', false)
            ->assertSee('assets/images/sphere-japan.webp', false)
            ->assertSee('data-country="Japan" data-plans="2"', false)
            ->assertSee('<span class="price-current">$12.99</span>', false)
            ->assertSee('<span class="price-original">$18.99</span>', false)
            ->assertDontSee('$34.99')                    // static sample price gone
            ->assertDontSee('SECRET_BUNDLE_JP')
            ->assertDontSee('7.1234');
    }

    public function test_new_esim_shows_real_counts_and_destinations(): void
    {
        $this->enableLiveCatalogue();
        $this->japanWithPlans();

        $this->get('/new-esim')->assertOk()
            ->assertSee('Showing 2 plans in 1 destination')
            ->assertSee('data-value="Japan">Japan</li>', false)
            ->assertDontSee('data-value="Brazil"', false)
            ->assertSee('href="'.url('/esim/japan').'" class="btn btn-primary"', false);
    }

    public function test_country_page_reuses_the_japan_design_with_database_plans(): void
    {
        $this->enableLiveCatalogue();
        [$japan] = $this->japanWithPlans();
        $usa = $this->country(['name' => 'United States', 'iso2' => 'US', 'slug' => 'united-states']);
        $this->sellablePlan(['slug' => 'us-3gb', 'data_amount_mb' => 3000], $usa, '9.00');

        $page = $this->get('/esim/japan')->assertOk()
            ->assertSee('css/japan-esim.css', false)
            ->assertSee('<h1 class="page-banner-title">Japan eSIM</h1>', false)
            ->assertSee('window.EAZY_PLANS', false)
            ->assertSee('5 GB - 7 Days')
            ->assertSee('Unlimited - 10 Days')
            ->assertSee('$12.99')
            ->assertDontSee('$654.99')                  // static sample data not used
            ->assertDontSee('SECRET_BUNDLE_JP')
            ->assertDontSee('7.1234')
            ->assertDontSee('markup')
            ->assertDontSee('provider_cost');

        $this->get('/esim/united-states')->assertOk()
            ->assertSee('United States eSIM')
            ->assertSee('UNITED STATES ESIM')
            ->assertSee('3 GB - 7 Days')
            ->assertDontSee('5 GB - 7 Days');
    }

    public function test_country_page_hides_the_toggle_for_a_data_type_without_plans(): void
    {
        $this->enableLiveCatalogue();
        $usa = $this->country(['name' => 'United States', 'iso2' => 'US', 'slug' => 'united-states']);
        $this->sellablePlan(['slug' => 'us-3gb'], $usa);

        $this->get('/esim/united-states')
            ->assertSee('class="toggle-tab-btn" data-type="unlimited" role="tab" aria-selected="false"  hidden', false)
            ->assertSee('class="toggle-tab-btn active" data-type="fixed"', false)
            ->assertSee('window.EAZY_INITIAL_TYPE = "fixed"', false);
    }

    public function test_old_japan_url_redirects_when_live(): void
    {
        $this->enableLiveCatalogue();
        $this->get('/japan-esim')->assertRedirect('/new-esim');   // no Japan plans yet

        $this->japanWithPlans();
        $this->get('/japan-esim')->assertRedirect('/esim/japan');
    }

    public function test_inactive_countries_and_countries_without_sellable_plans_return_404(): void
    {
        $this->enableLiveCatalogue();
        $this->country(['slug' => 'empty-land']);
        $closed = $this->country(['slug' => 'closed-land', 'is_active' => false]);
        $this->sellablePlan(country: $closed);

        $this->get('/esim/empty-land')->assertNotFound();
        $this->get('/esim/closed-land')->assertNotFound();
        $this->get('/esim/does-not-exist')->assertNotFound();
    }

    public function test_only_sellable_plans_appear_on_the_website(): void
    {
        $this->enableLiveCatalogue();
        $country = $this->country(['name' => 'Testland', 'slug' => 'testland']);
        $this->sellablePlan(['display_title' => 'VISIBLE PLAN'], $country);
        $this->sellablePlan(['display_title' => 'INACTIVE PLAN', 'is_active' => false], $country);
        $this->sellablePlan(['display_title' => 'UNAVAILABLE BUNDLE'], $country, bundle: ['is_available' => false]);
        $this->sellablePlan(['display_title' => 'PENDING PRICE'], $country, priceState: ['approval_status' => 'pending', 'is_active' => false]);
        $this->sellablePlan(['display_title' => 'EXPIRED PRICE'], $country, priceState: ['effective_from' => now()->subDays(5), 'effective_to' => now()->subDay()]);
        $this->sellablePlan(['display_title' => 'FUTURE PRICE'], $country, priceState: ['effective_from' => now()->addDay()]);
        $this->draftPlan(['display_title' => 'NO PRICE', 'is_active' => true], $country);

        $this->get('/esim/testland')->assertOk()
            ->assertSee('VISIBLE PLAN')
            ->assertDontSee('INACTIVE PLAN')->assertDontSee('UNAVAILABLE BUNDLE')->assertDontSee('PENDING PRICE')
            ->assertDontSee('EXPIRED PRICE')->assertDontSee('FUTURE PRICE')->assertDontSee('NO PRICE');
        $this->get('/new-esim')->assertSee('Showing 1 plan in 1 destination');
    }

    public function test_a_price_below_the_current_provider_cost_is_never_shown(): void
    {
        $this->enableLiveCatalogue();
        $country = $this->country(['slug' => 'testland']);
        $plan = $this->sellablePlan(['display_title' => 'COST ROSE'], $country, '10.00');
        $plan->providerBundle->update(['provider_cost' => '11.0000']);   // cost rose above the approved price

        $this->get('/esim/testland')->assertNotFound();
        $this->get('/')->assertDontSee('COST ROSE');
    }

    public function test_admin_entered_text_is_escaped_on_customer_pages(): void
    {
        $this->enableLiveCatalogue();
        $country = $this->country(['name' => '<b>Bold</b>land', 'slug' => 'boldland']);
        $this->sellablePlan(['display_title' => '<img src=x onerror=alert(1)>'], $country);

        $html = $this->get('/esim/boldland')->assertOk()->getContent();
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<b>Bold</b>', $html);
        $this->get('/')->assertDontSee('<b>Bold</b>land', false);
    }

    public function test_add_to_cart_behaviour_is_unchanged(): void
    {
        $this->enableLiveCatalogue();
        $this->japanWithPlans();

        // Still the existing client-side button (Phase 6 will connect it to the cart).
        $this->get('/esim/japan')->assertSee('id="addCartBtn"', false)->assertSee('js/japan-esim.js', false);
        $this->assertSame(0, \DB::table('cart_items')->count());
    }
}
