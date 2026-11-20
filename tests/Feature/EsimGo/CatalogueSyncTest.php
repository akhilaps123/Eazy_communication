<?php

namespace Tests\Feature\EsimGo;

use App\Models\IntegrationLog;
use App\Models\PlanPrice;
use App\Models\ProviderBundle;
use App\Services\EsimGo\CatalogueSynchroniser;
use App\Services\EsimGo\EsimGoException;
use Database\Factories\ProviderBundleFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class CatalogueSyncTest extends DatabaseTestCase
{
    use CatalogueFixtures;
    use EsimGoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEsimGo();
    }

    private function sync(bool $fresh = true, bool $dryRun = false): array
    {
        return app(CatalogueSynchroniser::class)->sync($fresh, $dryRun);
    }

    /** Fake the organisation endpoint and a catalogue answered page by page. */
    private function fakeCatalogue(array ...$pages): void
    {
        $sequence = Http::sequence();
        foreach ($pages as $page) {
            $sequence->push($page);
        }
        Http::fake([
            self::BASE.'/organisation' => $this->organisationResponse('USD'),
            self::BASE.'/catalogue*' => $sequence,
        ]);
    }

    private function catalogueRequests(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), '/catalogue'))
            ->map(fn ($pair) => $pair[0]->url())->values()->all();
    }

    public function test_all_pages_are_fetched_using_the_page_count(): void
    {
        $this->fakeCatalogue(
            ['bundles' => [$this->bundle('esim_1'), $this->bundle('esim_2')], 'pageCount' => 3],
            ['bundles' => [$this->bundle('esim_3'), $this->bundle('esim_4')], 'pageCount' => 3],
            ['bundles' => [$this->bundle('esim_5')], 'pageCount' => 3],
        );

        $stats = $this->sync();

        $this->assertSame([
            self::BASE.'/catalogue?page=1&perPage=2',
            self::BASE.'/catalogue?page=2&perPage=2',
            self::BASE.'/catalogue?page=3&perPage=2',
        ], $this->catalogueRequests());
        $this->assertSame([3, 5, 5, 0], [$stats['pages'], $stats['received'], $stats['created'], $stats['updated']]);
        $this->assertSame(5, ProviderBundle::count());
    }

    public function test_a_plain_array_catalogue_is_paged_until_an_empty_page(): void
    {
        $this->fakeCatalogue([$this->bundle('esim_1'), $this->bundle('esim_2')], [$this->bundle('esim_3')], []);

        $stats = $this->sync();

        $this->assertCount(3, $this->catalogueRequests());
        $this->assertSame([3, 3], [$stats['pages'], ProviderBundle::count()]);
    }

    public function test_pagination_stops_if_the_api_repeats_the_same_page(): void
    {
        $page = [$this->bundle('esim_1'), $this->bundle('esim_2')];
        $this->fakeCatalogue($page, $page, $page, $page);

        $stats = $this->sync();

        $this->assertCount(2, $this->catalogueRequests());
        $this->assertSame([2, 2], [$stats['duplicates'], ProviderBundle::count()]);
    }

    public function test_runaway_pagination_is_stopped_without_writing(): void
    {
        config(['esimgo.catalogue.max_pages' => 2]);
        $this->fakeCatalogue(
            ['bundles' => [$this->bundle('esim_1')], 'pageCount' => 9],
            ['bundles' => [$this->bundle('esim_2')], 'pageCount' => 9],
        );

        $this->expectException(EsimGoException::class);
        try {
            $this->sync();
        } finally {
            $this->assertSame(0, ProviderBundle::count());
        }
    }

    public function test_provider_fields_are_mapped_onto_provider_bundles(): void
    {
        $this->fakeCatalogue([
            $this->bundle('esim_3GB_30D_EU_V2', [
                'description' => 'eSIM, 3GB, 30 Days, Europe',
                'countries' => [['iso' => 'fr'], ['iso' => 'DE'], ['iso' => 'FR'], ['iso' => 'bad']],
                'dataAmount' => 3000, 'duration' => 30, 'price' => 4.255, 'speed' => ['4G', '5G'],
            ]),
            $this->bundle('esim_UL_7D_JP_V2', ['unlimited' => true, 'dataAmount' => -1, 'countries' => [['iso' => 'JP']]]),
        ], []);

        $this->sync();

        $eu = ProviderBundle::where('bundle_code', 'esim_3GB_30D_EU_V2')->sole();
        $this->assertSame(
            ['esimgo', 'esim_3GB_30D_EU_V2', 'eSIM, 3GB, 30 Days, Europe', 3000, false, 30, ['DE', 'FR'], '4G/5G', '4.2550', 'USD', true],
            [$eu->provider, $eu->name, $eu->description, $eu->data_amount_mb, $eu->is_unlimited, $eu->duration_days, $eu->country_codes, $eu->speed, $eu->provider_cost, $eu->provider_currency, $eu->is_available],
        );
        $this->assertNotNull($eu->last_synced_at);
        $this->assertArrayNotHasKey('countryNetworks', $eu->raw_payload);
        $this->assertSame('FixedCost', $eu->raw_payload['billingType']);

        $jp = ProviderBundle::where('bundle_code', 'esim_UL_7D_JP_V2')->sole();
        $this->assertSame([true, null], [$jp->is_unlimited, $jp->data_amount_mb]);
    }

    public function test_the_account_currency_can_be_set_in_config_instead_of_fetched(): void
    {
        config(['esimgo.currency' => 'eur']);
        $this->fakeCatalogue([$this->bundle('esim_1')], []);

        $this->sync();

        $this->assertSame('EUR', ProviderBundle::sole()->provider_currency);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/organisation'));
    }

    public function test_resync_updates_existing_records_without_duplicates(): void
    {
        $this->fakeCatalogue(
            [$this->bundle('esim_1', ['price' => 1.5]), $this->bundle('esim_2')], [],
            [$this->bundle('esim_1', ['price' => 1.75]), $this->bundle('esim_2')], [],
        );

        $first = $this->sync();
        $second = $this->sync();

        $this->assertSame([2, 0, 0], [$first['created'], $first['updated'], $first['unchanged']]);
        $this->assertSame([0, 1, 1], [$second['created'], $second['updated'], $second['unchanged']]);
        $this->assertSame(2, ProviderBundle::count());
        $this->assertSame('1.7500', ProviderBundle::where('bundle_code', 'esim_1')->value('provider_cost'));
    }

    public function test_duplicate_and_invalid_bundles_in_the_response_are_not_stored_twice(): void
    {
        $this->fakeCatalogue([
            $this->bundle('esim_1'), $this->bundle('ESIM_1'), $this->bundle('bad name!'), ['description' => 'no name'],
        ], []);

        $stats = $this->sync();

        $this->assertSame([4, 1, 1, 2], [$stats['received'], $stats['valid'], $stats['duplicates'], $stats['skipped_invalid']]);
        $this->assertSame(1, ProviderBundle::count());
    }

    public function test_existing_plans_retail_prices_and_admin_names_are_preserved(): void
    {
        $plan = $this->sellablePlan(['name' => 'UK 1GB'], price: '10.00', bundle: ['bundle_code' => 'esim_1GB_7D_GB_V2', 'name' => 'Admin label', 'provider_cost' => '5.0000']);
        $planBefore = $plan->fresh()->toArray();
        $pricesBefore = PlanPrice::orderBy('id')->get()->toArray();

        $this->fakeCatalogue([$this->bundle('esim_1GB_7D_GB_V2', ['price' => 1.23])], []);
        $stats = $this->sync();

        $this->assertSame([0, 1], [$stats['created'], $stats['updated']]);
        $this->assertSame($planBefore, $plan->fresh()->toArray());
        $this->assertSame($pricesBefore, PlanPrice::orderBy('id')->get()->toArray());
        $bundle = $plan->providerBundle()->sole();
        $this->assertSame(['Admin label', '1.2300', true], [$bundle->name, $bundle->provider_cost, $bundle->is_available]);
        $this->assertSame(1, ProviderBundle::count());
    }

    public function test_availability_rules(): void
    {
        $manual = ProviderBundleFactory::new()->create(['bundle_code' => 'AU-5GB-7D-TEST']);                                          // hand-entered, never synced
        $gone = ProviderBundleFactory::new()->create(['bundle_code' => 'esim_gone', 'last_synced_at' => now()->subDay()]);            // synced before, now missing
        $disabled = ProviderBundleFactory::new()->unavailable()->create(['bundle_code' => 'esim_back', 'last_synced_at' => now()->subDay()]);

        $this->fakeCatalogue([$this->bundle('esim_back'), $this->bundle('esim_new')], []);
        $stats = $this->sync();

        $this->assertSame(1, $stats['marked_unavailable']);
        $this->assertTrue($manual->fresh()->is_available);
        $this->assertNull($manual->fresh()->last_synced_at);
        $this->assertFalse($gone->fresh()->is_available);
        $this->assertFalse($disabled->fresh()->is_available);     // a sync never re-enables a bundle
        $this->assertTrue(ProviderBundle::where('bundle_code', 'esim_new')->value('is_available'));
    }

    public function test_an_empty_catalogue_marks_nothing_unavailable(): void
    {
        $synced = ProviderBundleFactory::new()->create(['bundle_code' => 'esim_1', 'last_synced_at' => now()->subDay()]);
        $this->fakeCatalogue([]);

        $stats = $this->sync();

        $this->assertSame(0, $stats['marked_unavailable']);
        $this->assertTrue($synced->fresh()->is_available);
    }

    public function test_a_failed_page_changes_nothing_and_is_not_cached(): void
    {
        $existing = ProviderBundleFactory::new()->create(['bundle_code' => 'esim_1', 'provider_cost' => '9.0000', 'last_synced_at' => now()->subDay()]);
        Http::fake([
            self::BASE.'/organisation' => $this->organisationResponse(),
            self::BASE.'/catalogue*' => Http::sequence()
                ->push([$this->bundle('esim_1', ['price' => 1]), $this->bundle('esim_2')])
                ->push(['message' => 'down'], 503)->push(['message' => 'down'], 503)->push(['message' => 'down'], 503),
        ]);

        try {
            $this->sync();
            $this->fail('Expected the sync to fail.');
        } catch (EsimGoException $e) {
            $this->assertSame(EsimGoException::UNAVAILABLE, $e->kind);
        }

        $this->assertSame(1, ProviderBundle::count());
        $this->assertSame('9.0000', $existing->fresh()->provider_cost);
        $this->assertNull(Cache::get(CatalogueSynchroniser::CACHE_KEY));
        $this->assertFalse(IntegrationLog::where('operation', 'catalogue_sync')->sole()->success);
        $this->assertKeyNeverLogged();
    }

    public function test_the_catalogue_is_cached_to_avoid_polling(): void
    {
        $this->fakeCatalogue([$this->bundle('esim_1')], [], [$this->bundle('esim_1')], []);

        $this->assertFalse($this->sync(fresh: false)['from_cache']);
        $this->assertTrue($this->sync(fresh: false)['from_cache']);
        $this->assertCount(2, $this->catalogueRequests());   // second sync made no catalogue requests

        $this->assertFalse($this->sync(fresh: true)['from_cache']);
        $this->assertCount(4, $this->catalogueRequests());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakeCatalogue([$this->bundle('esim_1')], []);

        $stats = $this->sync(dryRun: true);

        $this->assertSame([true, 1], [$stats['dry_run'], $stats['created']]);
        $this->assertSame(0, ProviderBundle::count());
    }

    public function test_concurrent_syncs_are_refused(): void
    {
        Http::fake();
        $lock = Cache::lock(CatalogueSynchroniser::LOCK_KEY, 60);
        $lock->get();

        try {
            $this->sync();
            $this->fail('Expected a lock refusal.');
        } catch (EsimGoException $e) {
            $this->assertStringContainsString('already running', $e->getMessage());
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
    }

    public function test_sync_command(): void
    {
        $this->fakeCatalogue([$this->bundle('esim_1')], []);

        $this->artisan('esimgo:sync-catalogue', ['--fresh' => true])
            ->expectsOutputToContain('Catalogue synchronised.')
            ->assertSuccessful();
        $this->assertSame(1, ProviderBundle::count());
    }

    public function test_sync_command_reports_a_missing_key(): void
    {
        config(['esimgo.api_key' => '']);
        Http::fake();

        $this->artisan('esimgo:sync-catalogue')->expectsOutputToContain('ESIMGO_API_KEY is empty')->assertFailed();
        Http::assertNothingSent();
    }
}
