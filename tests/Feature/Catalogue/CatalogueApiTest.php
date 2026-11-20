<?php

namespace Tests\Feature\Catalogue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Database\DatabaseTestCase;

class CatalogueApiTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    /** Words and values that must never appear in a public catalogue response. */
    private const FORBIDDEN = ['provider_cost', 'provider_currency', 'markup', 'pricing_method', 'approval', 'bundle_code',
        'raw_payload', 'provider_bundle', 'created_by', 'approved_by', 'calculated_at', 'SECRET_BUNDLE', '7.1234'];

    private function seedJapan(): array
    {
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'iso3' => 'JPN', 'slug' => 'japan', 'is_popular' => true]);
        $a = $this->sellablePlan(['slug' => 'jp-5gb', 'data_amount_mb' => 5000, 'validity_days' => 7, 'features' => ['Hotspot']], $japan, '12.99',
            ['compare_at_price' => '18.99'], ['bundle_code' => 'SECRET_BUNDLE_A', 'provider_cost' => '7.1234']);
        $b = $this->sellablePlan(['slug' => 'jp-20gb', 'data_amount_mb' => 20000, 'validity_days' => 30], $japan, '29.99', [], ['bundle_code' => 'SECRET_BUNDLE_B']);
        $c = $this->sellablePlan(['slug' => 'jp-unl', 'data_type' => 'unlimited', 'data_amount_mb' => null, 'validity_days' => 10], $japan, '44.99');

        return [$japan, $a, $b, $c];
    }

    private function assertNoInternalData(string $json): void
    {
        foreach (self::FORBIDDEN as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $json, "Public API leaked [{$word}]");
        }
    }

    public function test_countries_lists_only_countries_with_sellable_plans_and_safe_fields(): void
    {
        $this->seedJapan();
        $this->country(['name' => 'Emptyland', 'slug' => 'emptyland']);   // no plans

        $response = $this->getJson('/api/countries')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('currency', 'USD')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'japan')
            ->assertJsonPath('data.0.plans_count', 3)
            ->assertJsonPath('data.0.price_from', ['amount' => '12.99', 'currency' => 'USD', 'formatted' => '$12.99']);

        $this->assertSame(['iso2', 'iso3', 'name', 'slug', 'region', 'is_popular', 'flag_image_url', 'plans_count', 'price_from'], array_keys($response->json('data.0')));
        $this->assertNoInternalData($response->getContent());
    }

    public function test_country_plans_with_filters_and_sorting(): void
    {
        $this->seedJapan();

        $response = $this->getJson('/api/countries/japan/plans')->assertOk()
            ->assertJsonPath('country.slug', 'japan')
            ->assertJsonCount(3, 'data');
        $this->assertSame(['slug', 'name', 'title', 'description', 'coverage_type', 'region_name', 'data_type', 'data_amount_mb', 'data_label',
            'validity_days', 'network_speed', 'features', 'badge', 'price', 'countries'], array_keys($response->json('data.0')));
        $this->assertSame(['amount', 'currency', 'formatted', 'compare_at', 'compare_at_formatted'], array_keys($response->json('data.0.price')));
        $this->assertNoInternalData($response->getContent());

        $slugs = fn ($query) => array_column($this->getJson('/api/countries/japan/plans'.$query)->json('data'), 'slug');
        $this->assertSame(['jp-5gb', 'jp-20gb'], $slugs('?data_type=fixed&sort=price'));
        $this->assertSame(['jp-unl'], $slugs('?data_type=unlimited'));
        $this->assertSame(['jp-5gb', 'jp-20gb', 'jp-unl'], $slugs('?sort=price'));
        $this->assertSame(['jp-unl', 'jp-20gb', 'jp-5gb'], $slugs('?sort=-price'));
        $this->assertSame(['jp-5gb', 'jp-unl', 'jp-20gb'], $slugs('?sort=validity'));
        $this->assertSame(['jp-unl', 'jp-20gb', 'jp-5gb'], $slugs('?sort=-data'));   // unlimited is the largest

        $this->getJson('/api/countries/japan/plans?sort=cost')->assertStatus(422)->assertJsonPath('success', false);
        $this->getJson('/api/countries/japan/plans?data_type=lots')->assertStatus(422);
    }

    public function test_unknown_and_inactive_countries_return_404_json(): void
    {
        $closed = $this->country(['slug' => 'closed', 'is_active' => false]);
        $this->sellablePlan(country: $closed);

        $this->getJson('/api/countries/nowhere/plans')->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Country not found.']);
        $this->getJson('/api/countries/closed/plans')->assertNotFound();
    }

    public function test_plan_details(): void
    {
        $this->seedJapan();

        $response = $this->getJson('/api/plans/jp-5gb')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', '5 GB - 7 Days')
            ->assertJsonPath('data.data_label', '5 GB')
            ->assertJsonPath('data.data_amount_mb', 5000)
            ->assertJsonPath('data.validity_days', 7)
            ->assertJsonPath('data.features', ['Hotspot'])
            ->assertJsonPath('data.price.formatted', '$12.99')
            ->assertJsonPath('data.price.compare_at', '18.99')
            ->assertJsonPath('data.countries.0', ['iso2' => 'JP', 'name' => 'Japan', 'slug' => 'japan']);
        $this->assertNoInternalData($response->getContent());

        $this->getJson('/api/plans/nope')->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Plan not found.']);
    }

    public function test_non_sellable_plans_are_never_returned(): void
    {
        $country = $this->country(['slug' => 'testland']);
        $this->sellablePlan(['slug' => 'ok'], $country);
        $this->sellablePlan(['slug' => 'inactive', 'is_active' => false], $country);
        $this->sellablePlan(['slug' => 'unavailable'], $country, bundle: ['is_available' => false]);
        $this->sellablePlan(['slug' => 'pending'], $country, priceState: ['approval_status' => 'pending', 'is_active' => false]);
        $this->sellablePlan(['slug' => 'price-off'], $country, priceState: ['is_active' => false]);
        $this->sellablePlan(['slug' => 'expired'], $country, priceState: ['effective_from' => now()->subDays(5), 'effective_to' => now()->subDay()]);
        $this->sellablePlan(['slug' => 'future'], $country, priceState: ['effective_from' => now()->addDay()]);
        $this->sellablePlan(['slug' => 'eur-only'], $country, priceState: ['currency' => 'EUR']);
        $this->sellablePlan(['slug' => 'inactive-country'], $this->country(['is_active' => false]));
        $below = $this->sellablePlan(['slug' => 'below-cost'], $country, '10.00');
        $below->providerBundle->update(['provider_cost' => '11.0000']);

        $this->assertSame(['ok'], array_column($this->getJson('/api/countries/testland/plans')->json('data'), 'slug'));
        foreach (['inactive', 'unavailable', 'pending', 'price-off', 'expired', 'future', 'eur-only', 'inactive-country', 'below-cost'] as $slug) {
            $this->getJson("/api/plans/{$slug}")->assertNotFound();
        }
        $this->assertSame(1, $this->getJson('/api/countries')->json('data.0.plans_count'));
    }

    public function test_regional_plans_list_their_active_countries_only(): void
    {
        [$a, $b, $hidden] = [$this->country(['name' => 'Alpha', 'slug' => 'alpha']), $this->country(['name' => 'Beta', 'slug' => 'beta']), $this->country(['name' => 'Gamma', 'slug' => 'gamma'])];
        $plan = $this->sellablePlan(['slug' => 'regional', 'coverage_type' => 'regional', 'region_name' => 'Test Region'], $a);
        $plan->countries()->attach([$b->id, $hidden->id]);
        $hidden->update(['is_active' => false]);

        $this->assertSame(['Alpha', 'Beta'], array_column($this->getJson('/api/plans/regional')->json('data.countries'), 'name'));
        $this->getJson('/api/countries/beta/plans')->assertJsonPath('data.0.slug', 'regional');
        $this->assertSame(['alpha', 'beta'], array_column($this->getJson('/api/countries')->json('data'), 'slug'));
    }

    public function test_public_endpoints_need_no_login_and_use_the_api_rate_limit(): void
    {
        foreach (['api.countries.index', 'api.countries.plans', 'api.plans.show'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('throttle:api', $middleware);
            $this->assertNotContains('auth:sanctum', $middleware);
        }
        $this->getJson('/api/countries')->assertOk();
    }

    public function test_api_is_independent_of_the_website_live_switch(): void
    {
        $this->assertFalse(config('catalogue.live'));
        $this->seedJapan();

        $this->getJson('/api/countries')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_query_count_does_not_grow_with_the_number_of_plans(): void
    {
        $country = $this->country(['slug' => 'testland']);
        $this->sellablePlan(country: $country);

        $count = function (string $uri) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($uri)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $few = [$count('/api/countries'), $count('/api/countries/testland/plans')];

        for ($i = 0; $i < 15; $i++) {
            $this->sellablePlan(country: $i % 2 ? $country : $this->country());
        }
        $many = [$count('/api/countries'), $count('/api/countries/testland/plans')];

        $this->assertSame($few, $many, 'Catalogue endpoints must not run one query per plan (N+1).');
    }
}
