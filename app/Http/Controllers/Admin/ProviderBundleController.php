<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Plan;
use App\Models\ProviderBundle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin: read-only view of provider (eSIM Go) bundles. Provider-owned data —
 * cost, currency, countries, data, validity — is never edited here; synced
 * bundles are maintained by `php artisan esimgo:sync-catalogue`.
 */
class ProviderBundleController extends Controller
{
    public const DATA_FILTERS = [
        'unlimited' => 'Unlimited',
        'under1' => 'Under 1 GB',
        '1to5' => '1 – 5 GB',
        '5to20' => 'Over 5 – 20 GB',
        'over20' => 'Over 20 GB',
    ];

    public const VALIDITY_FILTERS = [
        '1to7' => '1 – 7 days',
        '8to15' => '8 – 15 days',
        '16to30' => '16 – 30 days',
        'over30' => 'Over 30 days',
    ];

    public function index(Request $request): View
    {
        $filters = self::filters($request);

        $bundles = self::filtered(ProviderBundle::query(), $filters)
            ->with('plan:id,provider_bundle_id,name,is_active')
            ->orderBy('bundle_code')
            ->paginate(25)
            ->withQueryString();

        return view('admin.provider-bundles.index', [
            'bundles' => $bundles,
            'filters' => $filters,
            'countries' => Country::query()->ordered()->get(['name', 'iso2']),
            'totals' => [
                'all' => ProviderBundle::query()->count(),
                'synced' => ProviderBundle::query()->whereNotNull('last_synced_at')->count(),
            ],
        ]);
    }

    public function show(ProviderBundle $providerBundle): View
    {
        $providerBundle->load('plan');
        $names = Country::query()->whereIn('iso2', $providerBundle->countryList())->pluck('name', 'iso2');

        return view('admin.provider-bundles.show', [
            'bundle' => $providerBundle,
            'countryNames' => $names,
            'selectable' => ProviderBundle::query()->selectableForPlan()->whereKey($providerBundle->id)->exists(),
        ]);
    }

    /**
     * JSON for the plan form's bundle picker: bundles that can be linked to a
     * plan (available, eSIM Go, not linked to another plan). At most 20.
     */
    public function search(Request $request): JsonResponse
    {
        $plan = $request->integer('plan') ? Plan::query()->find($request->integer('plan')) : null;

        $bundles = ProviderBundle::query()
            ->selectableForPlan($plan)
            ->search((string) $request->query('q'))
            ->forCountry((string) $request->query('country'))
            ->orderBy('bundle_code')
            ->limit(20)
            ->get();

        return response()->json(['data' => $bundles->map(fn (ProviderBundle $b) => self::summary($b))->all()]);
    }

    /** Safe, admin-only summary used by the picker (never sent to customers). */
    public static function summary(ProviderBundle $bundle): array
    {
        return [
            'id' => $bundle->id,
            'code' => $bundle->bundle_code,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'countries' => $bundle->countryList(),
            'data' => $bundle->dataLabel(),
            'validity' => $bundle->validityLabel(),
            'speed' => $bundle->speed,
            'cost' => $bundle->costLabel(),
            'source' => $bundle->isSynced() ? 'Synced' : 'Manual',
        ];
    }

    /** @return array<string, ?string> */
    public static function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($request->query($key), $allowed, true) ? $request->query($key) : null;
        $country = strtoupper(trim((string) $request->query('country')));

        return [
            'q' => trim((string) $request->query('q')),
            'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
            'data' => $pick('data', array_keys(self::DATA_FILTERS)),
            'validity' => $pick('validity', array_keys(self::VALIDITY_FILTERS)),
            'availability' => $pick('availability', ['available', 'unavailable']),
            'source' => $pick('source', ['synced', 'manual']),
            'linked' => $pick('linked', ['linked', 'unlinked']),
        ];
    }

    public static function filtered(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['q'] ?? '')
            ->forCountry($filters['country'] ?? null)
            ->when($filters['data'] ?? null, fn (Builder $q, string $data) => match ($data) {
                'unlimited' => $q->where('is_unlimited', true),
                'under1' => $q->where('is_unlimited', false)->where('data_amount_mb', '<', 1000),
                '1to5' => $q->where('is_unlimited', false)->whereBetween('data_amount_mb', [1000, 5000]),
                '5to20' => $q->where('is_unlimited', false)->where('data_amount_mb', '>', 5000)->where('data_amount_mb', '<=', 20000),
                'over20' => $q->where('is_unlimited', false)->where('data_amount_mb', '>', 20000),
            })
            ->when($filters['validity'] ?? null, fn (Builder $q, string $validity) => match ($validity) {
                '1to7' => $q->whereBetween('duration_days', [1, 7]),
                '8to15' => $q->whereBetween('duration_days', [8, 15]),
                '16to30' => $q->whereBetween('duration_days', [16, 30]),
                'over30' => $q->where('duration_days', '>', 30),
            })
            ->when(($filters['availability'] ?? null) === 'available', fn (Builder $q) => $q->where('is_available', true))
            ->when(($filters['availability'] ?? null) === 'unavailable', fn (Builder $q) => $q->where('is_available', false))
            ->when(($filters['source'] ?? null) === 'synced', fn (Builder $q) => $q->whereNotNull('last_synced_at'))
            ->when(($filters['source'] ?? null) === 'manual', fn (Builder $q) => $q->whereNull('last_synced_at'))
            ->when(($filters['linked'] ?? null) === 'linked', fn (Builder $q) => $q->has('plan'))
            ->when(($filters['linked'] ?? null) === 'unlinked', fn (Builder $q) => $q->doesntHave('plan'));
    }
}
