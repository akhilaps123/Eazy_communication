<?php

namespace Tests\Feature\EsimGo;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlanPrice;
use App\Models\ProviderBundle;
use App\Models\User;
use App\Services\EsimGo\CatalogueSynchroniser;
use App\Services\EsimGo\CountryMapper;
use App\Support\IsoCountries;
use Database\Factories\ProviderBundleFactory;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

/**
 * Phase 9B-2A: country mapping for eSIM Go bundles. Fixture shapes are copied
 * from the real GET /catalogue response (dry run of 19 Nov 2026):
 *   countries[]      {name, region, iso}  — a country code, a region name, or a special location
 *   roamingEnabled[] {name, region, iso}  — countries the bundle works in (regional coverage)
 */
class CountryMappingTest extends DatabaseTestCase
{
    use CatalogueFixtures;
    use EsimGoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEsimGo(['esimgo.currency' => 'USD']);
    }

    private static function place(string $iso, string $name = 'x', string $region = 'x'): array
    {
        return ['name' => $name, 'region' => $region, 'iso' => $iso];
    }

    /** @param list<string> $isos */
    private static function places(array $isos): array
    {
        return array_map(fn ($iso) => self::place($iso), $isos);
    }

    /** Real-shaped fixtures. */
    private function fixtures(): array
    {
        return [
            'france' => $this->bundle('esim_1GB_7D_FR_V2', ['countries' => [self::place('FR', 'France', 'Europe')], 'roamingEnabled' => []]),
            'europe' => $this->bundle('esim_1GB_7D_REUL_V2', [
                'description' => 'eSIM, 1GB, 7 Days, Europe Lite, V2',
                'countries' => [self::place('Europe Lite', 'Europe Lite', 'Europe Lite')],
                'roamingEnabled' => self::places(['AT', 'BE', 'DE', 'FR', 'IT', 'ES', 'NL']),
            ]),
            'asia' => $this->bundle('esim_1GB_7D_RAS_V2', [
                'description' => 'eSIM, 1GB, 7 Days, Asia, V2',
                'countries' => [self::place('Asia', 'Asia', 'Asia')],
                'roamingEnabled' => self::places(['AU', 'HK', 'ID', 'KR', 'SG', 'TH', 'VN']),
            ]),
            'global' => $this->bundle('esim_1GB_7D_RGB_V2', [
                'description' => 'eSIM, 1GB, 7 Days, Global, V2',
                'countries' => [self::place('Global', 'Global', 'Global')],
                // includes non-ISO values seen in real data: AN (withdrawn), IC (not assigned), CYP, US-HI
                'roamingEnabled' => self::places(['US', 'GB', 'JP', 'AN', 'IC', 'CYP', 'US-HI', 'FR', 'fr', ' DE ']),
            ]),
            'cis' => $this->bundle('esim_1GB_7D_RCI_V2', [
                'countries' => [self::place('CIS', 'CIS', 'CIS')],
                'roamingEnabled' => self::places(['KZ', 'KG', 'UZ']),
            ]),
            'cyprus' => $this->bundle('esim_1GB_7D_CY_V2', ['countries' => [self::place('CY', 'Cyprus')], 'roamingEnabled' => [self::place('CYP', 'Northern Cyprus')]]),
            'guernsey' => $this->bundle('esim_1GB_7D_GG_V2', ['countries' => [self::place('GG', 'Guernsey')], 'roamingEnabled' => [self::place('JE', 'Jersey')]]),
            'northern_cyprus' => $this->bundle('esim_1GB_7D_CYP_V2', [
                'description' => 'eSIM, 1GB, 7 Days, Northern Cyprus, V2',
                'countries' => [self::place('CYP', 'Northern Cyprus', 'Europe')],
                'roamingEnabled' => [self::place('CY', 'Cyprus', 'Middle East')],
            ]),
            'hawaii' => $this->bundle('esim_1GB_7D_US-HI_V2', [
                'description' => 'eSIM, 1GB, 7 Days, Hawaii, V2',
                'countries' => [self::place('US-HI', 'Hawaii', 'North America')],
                'roamingEnabled' => [],
            ]),
            'unknown' => $this->bundle('esim_1GB_7D_ATL_V2', [
                'countries' => [self::place('Atlantis', 'Atlantis', 'Nowhere'), self::place('XK', 'Kosovo')],
                'roamingEnabled' => [],
            ]),
        ];
    }

    private bool $faked = false;

    /** Each sync reads one page of bundles then an empty page; the fake answers up to 5 syncs. */
    private function syncFixtures(?array $bundles = null, bool $dryRun = false): array
    {
        if (! $this->faked) {
            $sequence = Http::sequence();
            foreach (range(1, 5) as $run) {
                $sequence->push(array_values($bundles ?? $this->fixtures()))->push([]);
            }
            Http::fake([self::BASE.'/catalogue*' => $sequence]);
            $this->faked = true;
        }

        return app(CatalogueSynchroniser::class)->sync(fresh: true, dryRun: $dryRun);
    }

    private function codes(string $bundleCode): array
    {
        return ProviderBundle::where('bundle_code', $bundleCode)->sole()->country_codes;
    }

    // ------------------------------------------------------------------ mapper rules

    public function test_iso_reference_list_has_the_249_assigned_codes(): void
    {
        $this->assertCount(249, IsoCountries::CODES);
        $this->assertCount(249, array_unique(IsoCountries::CODES));
        $this->assertTrue(IsoCountries::isValid('JP'));
        foreach (['AN', 'IC', 'XK', 'UK', 'EU', 'CYP', 'jp'] as $notAssigned) {
            $this->assertFalse(IsoCountries::isValid($notAssigned), $notAssigned);
        }
    }

    public function test_normal_single_country_bundle_keeps_its_country(): void
    {
        $this->assertSame(['codes' => ['FR'], 'resolved' => [], 'unmapped' => [], 'regional' => false, 'roaming_added' => 0],
            CountryMapper::map($this->fixtures()['france']));
    }

    public function test_europe_asia_and_cis_regional_bundles_use_roaming_enabled(): void
    {
        $f = $this->fixtures();

        $europe = CountryMapper::map($f['europe']);
        $this->assertSame(['AT', 'BE', 'DE', 'ES', 'FR', 'IT', 'NL'], $europe['codes']);
        $this->assertSame([['Europe Lite'], [], true], [$europe['resolved'], $europe['unmapped'], $europe['regional']]);

        $asia = CountryMapper::map($f['asia']);
        $this->assertSame(['AU', 'HK', 'ID', 'KR', 'SG', 'TH', 'VN'], $asia['codes']);
        $this->assertSame([['Asia'], true], [$asia['resolved'], $asia['regional']]);

        // A three-letter region name is still a region when the provider lists its countries.
        $this->assertSame([['KG', 'KZ', 'UZ'], ['CIS'], []], array_values(array_intersect_key(CountryMapper::map($f['cis']), array_flip(['codes', 'resolved', 'unmapped']))));
    }

    public function test_global_bundle_keeps_only_assigned_codes_without_duplicates_and_reports_the_rest(): void
    {
        $global = CountryMapper::map($this->fixtures()['global']);

        $this->assertSame(['DE', 'FR', 'GB', 'JP', 'US'], $global['codes']);              // "fr" and " DE " normalised; FR once
        $this->assertSame(['AN', 'CYP', 'IC', 'US-HI'], $global['unmapped']);              // never guessed, reported
        $this->assertSame([['Global'], true], [$global['resolved'], $global['regional']]);
    }

    public function test_single_country_bundles_include_explicit_roaming_countries(): void
    {
        $f = $this->fixtures();

        $this->assertSame(['codes' => ['GG', 'JE'], 'resolved' => [], 'unmapped' => [], 'regional' => false, 'roaming_added' => 1], CountryMapper::map($f['guernsey']));

        $cyprus = CountryMapper::map($f['cyprus']);
        $this->assertSame([['CY'], ['CYP'], 0], [$cyprus['codes'], $cyprus['unmapped'], $cyprus['roaming_added']]);
    }

    public function test_northern_cyprus_is_mapped_only_to_the_country_the_provider_lists(): void
    {
        $result = CountryMapper::map($this->fixtures()['northern_cyprus']);

        $this->assertSame(['CY'], $result['codes']);         // from roamingEnabled — not a guessed code for CYP itself
        $this->assertSame(['CYP'], $result['resolved']);
        $this->assertSame([], $result['unmapped']);
    }

    public function test_hawaii_is_not_mapped_to_the_whole_united_states(): void
    {
        $result = CountryMapper::map($this->fixtures()['hawaii']);

        $this->assertSame([], $result['codes']);             // US-HI is a subdivision: claiming "US" would overstate coverage
        $this->assertSame(['US-HI'], $result['unmapped']);
    }

    public function test_unknown_identifiers_are_reported_not_guessed(): void
    {
        $result = CountryMapper::map($this->fixtures()['unknown']);

        $this->assertSame([], $result['codes']);
        $this->assertSame(['Atlantis', 'XK'], $result['unmapped']);

        $this->assertSame([], CountryMapper::map([])['codes']);                                       // missing fields
        $this->assertSame([], CountryMapper::map(['countries' => 'oops', 'roamingEnabled' => [null, 5, ['iso' => '']]])['codes']);
    }

    // ------------------------------------------------------------------ through the sync

    public function test_sync_stores_mapped_countries_and_reports_unmapped_identifiers(): void
    {
        $stats = $this->syncFixtures();

        $this->assertSame(['AT', 'BE', 'DE', 'ES', 'FR', 'IT', 'NL'], $this->codes('esim_1GB_7D_REUL_V2'));
        $this->assertSame(['AU', 'HK', 'ID', 'KR', 'SG', 'TH', 'VN'], $this->codes('esim_1GB_7D_RAS_V2'));
        $this->assertSame(['DE', 'FR', 'GB', 'JP', 'US'], $this->codes('esim_1GB_7D_RGB_V2'));
        $this->assertSame(['GG', 'JE'], $this->codes('esim_1GB_7D_GG_V2'));
        $this->assertSame(['CY'], $this->codes('esim_1GB_7D_CYP_V2'));
        $this->assertSame([], $this->codes('esim_1GB_7D_US-HI_V2'));

        // multi-country: Europe, Asia, Global, CIS, Guernsey+Jersey · via roaming: Europe, Asia, Global, CIS, Northern Cyprus
        $this->assertSame([10, 8, 5, 5, 1, 2], [
            $stats['created'], $stats['with_countries'], $stats['multi_country'], $stats['regional_via_roaming'],
            $stats['extra_roaming_countries'], $stats['without_countries'],
        ]);
        $this->assertSame(['AN' => 1, 'Atlantis' => 1, 'CYP' => 2, 'IC' => 1, 'US-HI' => 2, 'XK' => 1], $stats['country_report']['unmapped']);
        $this->assertSame(['Asia' => 1, 'CIS' => 1, 'CYP' => 1, 'Europe Lite' => 1, 'Global' => 1], $stats['country_report']['resolved']);

        // The original provider values are kept, and the report is never stored as a column.
        $raw = ProviderBundle::where('bundle_code', 'esim_1GB_7D_REUL_V2')->sole()->raw_payload;
        $this->assertSame('Europe Lite', $raw['countries'][0]['iso']);
        $this->assertArrayNotHasKey('mapping', ProviderBundle::first()->getAttributes());
    }

    public function test_repeated_syncs_do_not_duplicate_bundles_or_countries(): void
    {
        $first = $this->syncFixtures();
        $before = ProviderBundle::orderBy('id')->get(['id', 'bundle_code', 'country_codes'])->toArray();

        $second = $this->syncFixtures();

        $this->assertSame([10, 0], [$first['created'], $first['updated']]);
        $this->assertSame([0, 0, 10], [$second['created'], $second['updated'], $second['unchanged']]);
        $this->assertSame($before, ProviderBundle::orderBy('id')->get(['id', 'bundle_code', 'country_codes'])->toArray());
        foreach (ProviderBundle::all() as $bundle) {
            $this->assertSame(array_values(array_unique($bundle->country_codes)), $bundle->country_codes, $bundle->bundle_code);
        }
    }

    public function test_bundles_synced_with_the_old_rules_get_their_countries_filled_in(): void
    {
        // As stored by the v1 rules: regional bundle with no countries.
        $old = ProviderBundleFactory::new()->create(['bundle_code' => 'esim_1GB_7D_REUL_V2', 'country_codes' => [], 'last_synced_at' => now()->subDay()]);

        $stats = $this->syncFixtures([$this->fixtures()['europe']]);

        $this->assertSame([0, 1], [$stats['created'], $stats['updated']]);
        $this->assertSame(['AT', 'BE', 'DE', 'ES', 'FR', 'IT', 'NL'], $old->fresh()->country_codes);
    }

    public function test_dry_run_reports_mapping_without_writing(): void
    {
        $stats = $this->syncFixtures(dryRun: true);

        $this->assertSame([true, 10, 5], [$stats['dry_run'], $stats['created'], $stats['multi_country']]);
        $this->assertSame(0, ProviderBundle::count());
    }

    public function test_manual_bundle_plan_prices_and_historical_orders_are_preserved(): void
    {
        $plan = $this->sellablePlan(['name' => 'Japan unlimited 7 days'], price: '19.99', bundle: ['bundle_code' => 'AU-5GB-7D-TEST', 'provider_cost' => '4.5000', 'country_codes' => null]);
        $customer = User::factory()->create();
        $order = Order::create(['order_number' => 'EC-TEST-1', 'user_id' => $customer->id, 'currency' => 'USD', 'subtotal' => '19.99', 'total' => '19.99', 'billing_name' => 'A', 'billing_email' => 'a@example.test']);
        $item = OrderItem::create(['order_id' => $order->id, 'plan_id' => $plan->id, 'plan_name' => $plan->name, 'bundle_code' => 'AU-5GB-7D-TEST',
            'unit_price' => '19.99', 'quantity' => 1, 'line_total' => '19.99', 'unit_provider_cost' => '4.5000', 'provider_currency' => 'USD']);

        $snapshot = fn () => [
            ProviderBundle::where('bundle_code', 'AU-5GB-7D-TEST')->sole()->toArray(),
            $plan->fresh()->toArray(), $plan->countries()->pluck('countries.id')->all(),
            PlanPrice::orderBy('id')->get()->toArray(), $order->fresh()->toArray(), $item->fresh()->toArray(),
        ];
        $before = $snapshot();

        $this->syncFixtures();
        $this->syncFixtures();

        $this->assertSame($before, $snapshot());
        $this->assertSame(11, ProviderBundle::count());   // 10 synced + the manual one, no duplicates
    }

    public function test_command_lists_mapped_and_unmapped_identifiers(): void
    {
        Http::fake([self::BASE.'/catalogue*' => Http::sequence()->push(array_values($this->fixtures()))->push([])]);

        $this->artisan('esimgo:sync-catalogue', ['--dry-run' => true, '--fresh' => true])
            ->expectsOutputToContain('Dry run — nothing was written.')
            ->expectsOutputToContain('Asia (1), CIS (1), CYP (1), Europe Lite (1), Global (1)')
            ->expectsOutputToContain('Identifiers NOT mapped to a country — review (bundles): AN (1), Atlantis (1), CYP (2), IC (1), US-HI (2), XK (1)')
            ->assertSuccessful();
    }

    public function test_bundle_details_page_shows_unmapped_locations_for_review(): void
    {
        $this->actingAs($this->admin());
        $this->syncFixtures();
        $hawaii = ProviderBundle::where('bundle_code', 'esim_1GB_7D_US-HI_V2')->sole();
        $europe = ProviderBundle::where('bundle_code', 'esim_1GB_7D_REUL_V2')->sole();

        $this->get("/admin/provider-bundles/{$hawaii->id}")->assertOk()->assertSee('Not mapped')->assertSee('US-HI');
        $this->get("/admin/provider-bundles/{$europe->id}")->assertOk()->assertSee('Provider location')->assertSee('Europe Lite')->assertDontSee('Not mapped');
        $this->get('/admin/provider-bundles?country=NL')->assertSee('esim_1GB_7D_REUL_V2')->assertDontSee('esim_1GB_7D_RAS_V2');
    }
}
