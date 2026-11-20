<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only customer catalogue (website pages and public API).
 *
 * A plan is sellable only when (Phase 5 rules):
 *   1. the plan is active,
 *   2. its provider bundle is available,
 *   3. it covers at least one active country,
 *   4. it has an applicable, approved price in the display currency (USD),
 *   5. and that price is not below the provider cost (same currency only).
 *
 * Everything is loaded with a fixed number of queries (no N+1). Provider
 * cost is used here only for the below-cost check and is never returned.
 */
class CatalogueService
{
    public function currency(): string
    {
        return strtoupper((string) config('catalogue.currency', 'USD'));
    }

    public function isLive(): bool
    {
        return (bool) config('catalogue.live', false);
    }

    /**
     * Sellable plans, each with its current price set as the "cataloguePrice" relation
     * and only its active countries loaded.
     *
     * @param  list<int>|null  $planIds  limit to these plans (used by the cart and checkout)
     * @return Collection<int, Plan>
     */
    public function sellablePlans(?Country $country = null, ?array $planIds = null): Collection
    {
        $currency = $this->currency();

        $plans = Plan::query()
            ->sellable($currency)
            ->when($country, fn (Builder $q) => $q->whereHas('countries', fn (Builder $c) => $c->whereKey($country->getKey())))
            ->when($planIds !== null, fn (Builder $q) => $q->whereKey($planIds))
            ->with([
                'providerBundle',
                'countries' => fn ($q) => $q->where('countries.is_active', true)->ordered(),
                'prices' => fn ($q) => $q->where('currency', $currency)->applicable()->orderByDesc('effective_from'),
            ])
            ->orderBy('plans.sort_order')
            ->orderBy('plans.id')
            ->get();

        return $plans
            ->filter(function (Plan $plan) {
                $price = $plan->prices->first();
                if ($price === null || $plan->isPriceBelowCost($price)) {
                    return false;   // rule 5: never sell below cost
                }
                $plan->setRelation('cataloguePrice', $price);

                return true;
            })
            ->values();
    }

    public function findSellablePlan(string $slug): ?Plan
    {
        return $this->sellablePlans()->firstWhere('slug', $slug);
    }

    /**
     * Active countries that have at least one sellable plan, with plan count,
     * cheapest plan and "from" price. Popular countries first.
     *
     * @return Collection<int, array{country: Country, plans: Collection, plans_count: int, cheapest: Plan, has_best_seller: bool, has_5g: bool}>
     */
    public function destinations(?Collection $plans = null): Collection
    {
        $plans ??= $this->sellablePlans();
        $byCountry = [];

        foreach ($plans as $plan) {
            foreach ($plan->countries as $country) {
                $byCountry[$country->id]['country'] ??= $country;
                $byCountry[$country->id]['plans'][] = $plan;
            }
        }

        return collect($byCountry)
            ->map(function (array $entry) {
                $plans = collect($entry['plans']);
                $cheapest = $plans->reduce(fn (?Plan $best, Plan $plan) => $best === null
                    || bccomp($this->amount($plan->cataloguePrice), $this->amount($best->cataloguePrice), 2) < 0 ? $plan : $best);

                return [
                    'country' => $entry['country'],
                    'plans' => $plans,
                    'plans_count' => $plans->count(),
                    'cheapest' => $cheapest,
                    'has_best_seller' => $plans->contains(fn (Plan $p) => $p->badge === 'best_seller'),
                    'has_5g' => $plans->contains(fn (Plan $p) => str_contains(strtoupper((string) $p->network_speed), '5G')),
                ];
            })
            ->sortBy([
                fn ($a, $b) => (int) $b['country']->is_popular <=> (int) $a['country']->is_popular,
                fn ($a, $b) => $a['country']->sort_order <=> $b['country']->sort_order,
                fn ($a, $b) => strcasecmp($a['country']->name, $b['country']->name),
            ])
            ->values();
    }

    /**
     * Customer-safe price details. Never includes cost, markup or method.
     *
     * @return array{amount: string, currency: string, formatted: string, compare_at: ?string, compare_at_formatted: ?string}
     */
    public function priceData(PlanPrice $price): array
    {
        $amount = $this->amount($price);
        $compare = $price->compare_at_price !== null && bccomp((string) $price->compare_at_price, $amount, 2) > 0
            ? (string) $price->compare_at_price
            : null;   // a crossed-out price is only shown when it is higher

        return [
            'amount' => $amount,
            'currency' => $price->currency,
            'formatted' => self::money($amount, $price->currency),
            'compare_at' => $compare,
            'compare_at_formatted' => $compare === null ? null : self::money($compare, $price->currency),
        ];
    }

    /** The amount the customer pays (the stored result of the pricing rule). */
    public function amount(PlanPrice $price): string
    {
        return PlanPrice::roundMoney((string) ($price->calculated_price ?? $price->manual_price ?? '0'));
    }

    /** "$1,234.50" — string based, no floating point. */
    public static function money(string $amount, string $currency): string
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $whole = ltrim($whole, '0') ?: '0';
        $whole = strrev(implode(',', str_split(strrev($whole), 3)));
        $symbol = ['USD' => '$'][strtoupper($currency)] ?? strtoupper($currency).' ';

        return ($negative ? '-' : '').$symbol.$whole.'.'.$fraction;
    }
}
