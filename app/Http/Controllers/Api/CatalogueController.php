<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Resources\PlanResource;
use App\Models\Country;
use App\Models\Plan;
use App\Services\CatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public catalogue API (no login; "api" rate limit). Lists sellable plans only.
 * Not affected by the website's live-catalogue switch: what is listed here is
 * controlled by plan activation and price approval in the admin panel.
 */
class CatalogueController extends Controller
{
    public const SORTS = ['price', '-price', 'validity', '-validity', 'data', '-data'];

    public function __construct(private readonly CatalogueService $catalogue) {}

    /** GET /api/countries */
    public function countries(): JsonResponse
    {
        return CountryResource::collection($this->catalogue->destinations())
            ->additional(['success' => true, 'currency' => $this->catalogue->currency()])
            ->response();
    }

    /** GET /api/countries/{slug}/plans?data_type=fixed|unlimited&sort=price|-price|validity|-validity|data|-data */
    public function countryPlans(Request $request, string $slug): JsonResponse
    {
        $filters = $request->validate([
            'data_type' => ['nullable', Rule::in(Plan::DATA_TYPES)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ], [
            'data_type.in' => 'data_type must be "fixed" or "unlimited".',
            'sort.in' => 'sort must be one of: '.implode(', ', self::SORTS).'.',
        ]);

        $country = Country::query()->active()->where('slug', $slug)->first();
        if ($country === null) {
            return response()->json(['success' => false, 'message' => 'Country not found.'], 404);
        }

        $plans = $this->catalogue->sellablePlans($country)
            ->when($filters['data_type'] ?? null, fn ($plans, $type) => $plans->where('data_type', $type)->values());
        $plans = $this->sort($plans, $filters['sort'] ?? null);

        return PlanResource::collection($plans)
            ->additional([
                'success' => true,
                'currency' => $this->catalogue->currency(),
                'country' => ['iso2' => $country->iso2, 'name' => $country->name, 'slug' => $country->slug],
            ])
            ->response();
    }

    /** GET /api/plans/{slug} */
    public function plan(string $slug): JsonResponse
    {
        $plan = $this->catalogue->findSellablePlan($slug);
        if ($plan === null) {
            return response()->json(['success' => false, 'message' => 'Plan not found.'], 404);
        }

        return (new PlanResource($plan))->additional(['success' => true])->response();
    }

    /** Sorting with bcmath for prices; unlimited data sorts as the largest. */
    private function sort($plans, ?string $sort)
    {
        if ($sort === null) {
            return $plans->values();
        }

        $descending = str_starts_with($sort, '-');
        $key = ltrim($sort, '-');

        $sorted = $plans->sort(function (Plan $a, Plan $b) use ($key) {
            return match ($key) {
                'price' => bccomp($this->catalogue->amount($a->cataloguePrice), $this->catalogue->amount($b->cataloguePrice), 2),
                'validity' => $a->validity_days <=> $b->validity_days,
                'data' => ($a->data_type === Plan::DATA_UNLIMITED ? PHP_INT_MAX : (int) $a->data_amount_mb)
                    <=> ($b->data_type === Plan::DATA_UNLIMITED ? PHP_INT_MAX : (int) $b->data_amount_mb),
            };
        });

        return ($descending ? $sorted->reverse() : $sorted)->values();
    }
}
