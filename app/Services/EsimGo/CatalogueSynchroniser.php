<?php

namespace App\Services\EsimGo;

use App\Models\IntegrationLog;
use App\Models\ProviderBundle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Copies the eSIM Go bundle catalogue into the existing provider_bundles table.
 *
 * What it changes — provider facts only, on provider_bundles:
 *   description, data, validity, countries, speed, provider cost + currency,
 *   raw payload, last_synced_at. New bundles are created (not for sale: a bundle
 *   is only sold once an admin creates a plan and an approved price for it).
 *
 * What it never changes: plans, plan_prices (retail prices), countries,
 * orders, payments; a bundle's admin-set name; and it never re-enables a bundle.
 *
 * Availability: a bundle that this sync has seen before and is now missing
 * from a COMPLETE catalogue is marked unavailable. Bundles that were entered
 * by hand and never matched the provider catalogue are left alone.
 *
 * The fetched catalogue is cached (config esimgo.catalogue.cache_ttl), so
 * repeated syncs do not poll eSIM Go; pass $fresh to bypass the cache.
 */
class CatalogueSynchroniser
{
    // v2: country mapping uses roamingEnabled (Phase 9B-2A); copies cached with the v1 rules are never reused.
    public const CACHE_KEY = 'esimgo:catalogue:v2';

    public const LOCK_KEY = 'esimgo:catalogue-sync';

    public function __construct(private readonly EsimGoClient $client) {}

    /**
     * Fetch every page (or reuse the cached copy) and normalise it.
     *
     * @return array{bundles: list<array<string, mixed>>, currency: string, pages: int, received: int, duplicates: int, skipped: int, fetched_at: string, from_cache: bool}
     */
    public function fetchCatalogue(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && isset($cached['bundles'], $cached['currency'])) {
                return ['from_cache' => true] + $cached;
            }
        }

        $currency = strtoupper(trim((string) config('esimgo.currency')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = (string) $this->client->organisation()['currency'];
            if ($currency === '') {
                throw new EsimGoException('Could not determine the eSIM Go account currency. Set ESIMGO_CURRENCY.', EsimGoException::INVALID_RESPONSE);
            }
        }

        $perPage = max(1, (int) config('esimgo.catalogue.per_page', 100));
        $maxPages = max(1, (int) config('esimgo.catalogue.max_pages', 500));
        $bundles = [];
        $received = $duplicates = $skipped = $pages = 0;
        $previousSignature = null;

        for ($page = 1; ; $page++) {
            if ($page > $maxPages) {
                throw new EsimGoException("The eSIM Go catalogue has more than {$maxPages} pages; stopped without changing anything.", EsimGoException::INVALID_RESPONSE);
            }

            $result = $this->client->cataloguePage($page, $perPage);
            $pages++;

            foreach ($result['bundles'] as $raw) {
                $received++;
                $attributes = self::normalise($raw);
                if ($attributes === null) {
                    $skipped++;

                    continue;
                }
                $key = strtolower($attributes['bundle_code']);
                if (isset($bundles[$key])) {
                    $duplicates++;

                    continue;
                }
                $bundles[$key] = $attributes;
            }

            $count = count($result['bundles']);
            $signature = md5((string) json_encode(array_map(fn ($b) => $b['name'] ?? null, $result['bundles'])));
            $done = $result['pageCount'] !== null
                ? $page >= $result['pageCount']
                : $signature === $previousSignature;   // no page count: stop at an empty page or when the API repeats a page
            $previousSignature = $signature;
            if ($done || $count === 0) {
                break;
            }

            Sleep::for((int) config('esimgo.catalogue.page_delay_ms', 250))->milliseconds();
        }

        $catalogue = [
            'bundles' => array_values($bundles),
            'currency' => $currency,
            'pages' => $pages,
            'received' => $received,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'countries' => self::countryReport($bundles),
            'fetched_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $catalogue, (int) config('esimgo.catalogue.cache_ttl', 21600));

        return ['from_cache' => false] + $catalogue;
    }

    /**
     * @return array<string, int|bool|string>
     */
    public function sync(bool $fresh = false, bool $dryRun = false): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 900);
        if (! $lock->get()) {
            throw new EsimGoException('A catalogue sync is already running.', EsimGoException::REQUEST);
        }

        $started = microtime(true);

        try {
            // Everything is fetched before anything is written: a failed page changes nothing.
            $catalogue = $this->fetchCatalogue($fresh);

            $stats = [
                'from_cache' => $catalogue['from_cache'],
                'pages' => $catalogue['pages'],
                'received' => $catalogue['received'],
                'valid' => count($catalogue['bundles']),
                'duplicates' => $catalogue['duplicates'],
                'skipped_invalid' => $catalogue['skipped'],
                'currency' => $catalogue['currency'],
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'marked_unavailable' => 0,
                'dry_run' => $dryRun,
                // Country mapping summary (details: $stats['country_report']).
                'with_countries' => $catalogue['countries']['with_countries'],
                'multi_country' => $catalogue['countries']['multi_country'],
                'regional_via_roaming' => $catalogue['countries']['regional'],
                'extra_roaming_countries' => $catalogue['countries']['with_extra_roaming'],
                'without_countries' => $catalogue['countries']['without_countries'],
                'country_report' => [
                    'resolved' => $catalogue['countries']['resolved'],
                    'unmapped' => $catalogue['countries']['unmapped'],
                ],
            ];

            $apply = function () use ($catalogue, $dryRun, &$stats) {
                $now = now();
                $existing = ProviderBundle::query()->where('provider', ProviderBundle::PROVIDER_ESIMGO)->get()
                    ->keyBy(fn (ProviderBundle $b) => strtolower($b->bundle_code));
                $seen = [];

                foreach ($catalogue['bundles'] as $attributes) {
                    $key = strtolower($attributes['bundle_code']);
                    $seen[$key] = true;
                    $values = Arr::except($attributes, ['bundle_code', 'mapping']) + ['provider_currency' => $catalogue['currency']];
                    if ($values['provider_cost'] === null) {
                        unset($values['provider_cost'], $values['provider_currency']);   // keep what we have
                    }

                    $bundle = $existing->get($key);
                    if ($bundle === null) {
                        $bundle = new ProviderBundle([
                            'provider' => ProviderBundle::PROVIDER_ESIMGO,
                            'bundle_code' => $attributes['bundle_code'],
                            'name' => $attributes['bundle_code'],
                            'is_available' => true,
                        ] + $values);
                        $stats['created']++;
                    } else {
                        $bundle->fill($values);
                        $bundle->isDirty() ? $stats['updated']++ : $stats['unchanged']++;
                    }

                    $bundle->last_synced_at = $now;
                    if (! $dryRun) {
                        $bundle->save();
                    }
                }

                // Only after a complete, non-empty catalogue; only bundles this sync manages.
                if ($seen !== []) {
                    foreach ($existing as $key => $bundle) {
                        if (! isset($seen[$key]) && $bundle->last_synced_at !== null && $bundle->is_available) {
                            $stats['marked_unavailable']++;
                            if (! $dryRun) {
                                $bundle->forceFill(['is_available' => false])->save();
                            }
                        }
                    }
                }
            };

            $dryRun ? $apply() : DB::transaction($apply);

            IntegrationLog::query()->create([
                'service' => IntegrationLog::SERVICE_ESIMGO,
                'operation' => $dryRun ? 'catalogue_sync_dry_run' : 'catalogue_sync',
                'success' => true,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'response_body' => $stats,
            ]);

            return $stats;
        } catch (EsimGoException $e) {
            IntegrationLog::query()->create([
                'service' => IntegrationLog::SERVICE_ESIMGO,
                'operation' => $dryRun ? 'catalogue_sync_dry_run' : 'catalogue_sync',
                'success' => false,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Map one eSIM Go bundle to provider_bundles attributes, or null when unusable.
     *
     * @return array<string, mixed>|null
     */
    public static function normalise(array $raw): ?array
    {
        $name = is_string($raw['name'] ?? null) ? trim($raw['name']) : '';
        if (! preg_match(EsimGoClient::BUNDLE_NAME_PATTERN, $name)) {
            return null;
        }

        $unlimited = filter_var($raw['unlimited'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data = is_numeric($raw['dataAmount'] ?? null) ? (int) $raw['dataAmount'] : null;   // MB
        $duration = is_numeric($raw['duration'] ?? null) ? (int) $raw['duration'] : null;    // days
        $price = is_numeric($raw['price'] ?? null) && (float) $raw['price'] >= 0 ? number_format((float) $raw['price'], 4, '.', '') : null;

        // Countries: main country codes + the provider's roamingEnabled list (see CountryMapper).
        $mapping = CountryMapper::map($raw);

        $speed = array_filter((array) ($raw['speed'] ?? []), fn ($s) => is_string($s) && $s !== '');
        $description = is_string($raw['description'] ?? null) ? mb_substr(trim($raw['description']), 0, 2000) : null;

        return [
            'bundle_code' => $name,
            'description' => $description === '' ? null : $description,
            'is_unlimited' => $unlimited,
            'data_amount_mb' => ! $unlimited && $data !== null && $data > 0 ? min($data, 4294967295) : null,
            'duration_days' => $duration !== null && $duration > 0 ? min($duration, 65535) : null,
            'country_codes' => $mapping['codes'],
            'speed' => $speed === [] ? null : mb_substr(implode('/', $speed), 0, 50),
            'provider_cost' => $price,
            // Network lists can be very large; everything else (incl. the original
            // countries / roamingEnabled values) is kept for reference.
            'raw_payload' => Arr::except($raw, ['countryNetworks']),
            // Report only — never stored as a column.
            'mapping' => Arr::except($mapping, ['codes']),
        ];
    }

    /**
     * Country-mapping summary for a fetched catalogue.
     *
     * @param  array<string, array<string, mixed>>  $bundles
     * @return array{with_countries: int, multi_country: int, regional: int, with_extra_roaming: int, without_countries: int, resolved: array<string, int>, unmapped: array<string, int>}
     */
    public static function countryReport(array $bundles): array
    {
        $report = ['with_countries' => 0, 'multi_country' => 0, 'regional' => 0, 'with_extra_roaming' => 0, 'without_countries' => 0, 'resolved' => [], 'unmapped' => []];

        foreach ($bundles as $bundle) {
            $count = count($bundle['country_codes']);
            $count > 0 ? $report['with_countries']++ : $report['without_countries']++;
            $count > 1 && $report['multi_country']++;
            ($bundle['mapping']['regional'] ?? false) && $report['regional']++;
            ($bundle['mapping']['roaming_added'] ?? 0) > 0 && $report['with_extra_roaming']++;
            foreach (['resolved', 'unmapped'] as $kind) {
                foreach ($bundle['mapping'][$kind] ?? [] as $identifier) {
                    $report[$kind][$identifier] = ($report[$kind][$identifier] ?? 0) + 1;   // identifier => number of bundles
                }
            }
        }
        ksort($report['resolved']);
        ksort($report['unmapped']);

        return $report;
    }
}
