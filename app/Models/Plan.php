<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer-facing eSIM plan.
 */
#[Fillable([
    'provider_bundle_id', 'slug', 'name', 'display_title', 'description', 'coverage_type',
    'region_name', 'data_type', 'data_amount_mb', 'validity_days', 'network_speed', 'features',
    'badge', 'brand_label', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    public const COVERAGE_LOCAL = 'local';

    public const COVERAGE_REGIONAL = 'regional';

    public const COVERAGE_GLOBAL = 'global';

    public const DATA_FIXED = 'fixed';

    public const DATA_UNLIMITED = 'unlimited';

    public const COVERAGE_TYPES = [self::COVERAGE_LOCAL, self::COVERAGE_REGIONAL, self::COVERAGE_GLOBAL];

    public const DATA_TYPES = [self::DATA_FIXED, self::DATA_UNLIMITED];

    /** Badge values shown on cards (existing design: "Popular", "Best Seller"). */
    public const BADGES = ['popular' => 'Popular', 'best_seller' => 'Best Seller'];

    /** Decision D9: 1 GB = 1,000 MB. */
    public const MB_PER_GB = 1000;

    protected function casts(): array
    {
        return [
            'data_amount_mb' => 'integer',
            'validity_days' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function providerBundle(): BelongsTo
    {
        return $this->belongsTo(ProviderBundle::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The price row that applies now in the given currency (planning report D4):
     * active, approved, inside its effective period; the latest effective_from wins.
     * Returns null when the plan is not for sale in that currency.
     */
    public function currentPrice(string $currency, ?DateTimeInterface $at = null): ?PlanPrice
    {
        return $this->prices()
            ->where('currency', strtoupper($currency))
            ->applicable($at)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Sellable plans (Phase 5): active, provider bundle available, at least one
     * active country, and an applicable approved price in the given currency.
     * (The below-cost check needs the loaded price, see isPriceBelowCost().)
     */
    public function scopeSellable(Builder $query, string $currency): Builder
    {
        $currency = strtoupper($currency);

        return $query
            ->where('plans.is_active', true)
            ->whereHas('providerBundle', fn (Builder $q) => $q->where('is_available', true))
            ->whereHas('countries', fn (Builder $q) => $q->where('countries.is_active', true))
            ->whereHas('prices', fn (Builder $q) => $q->where('currency', $currency)->applicable());
    }

    /**
     * Reasons this plan cannot be sold (empty array = sellable).
     * Used before activating a plan in the admin panel.
     *
     * @return list<string>
     */
    public function sellabilityProblems(string $currency): array
    {
        $this->loadMissing('providerBundle');
        $problems = [];

        if (! $this->providerBundle?->is_available) {
            $problems[] = 'The provider bundle is marked as not available.';
        }

        if (! $this->countries()->where('countries.is_active', true)->exists()) {
            $problems[] = 'The plan does not cover any active country.';
        }

        $price = $this->currentPrice($currency);
        if ($price === null) {
            $problems[] = "There is no approved, active {$currency} price in effect right now.";
        } elseif ($this->isPriceBelowCost($price)) {
            $problems[] = 'The current price is below the provider cost.';
        }

        return $problems;
    }

    /**
     * True when the price's selling amount is below the provider cost
     * (only comparable when both use the same currency — no conversion).
     */
    public function isPriceBelowCost(PlanPrice $price): bool
    {
        $bundle = $this->providerBundle;
        $amount = $price->calculated_price ?? $price->manual_price;

        if ($bundle === null || $bundle->provider_cost === null || $amount === null) {
            return false;
        }

        if (strtoupper((string) $bundle->provider_currency) !== strtoupper((string) $price->currency)) {
            return false;
        }

        return bccomp((string) $amount, (string) $bundle->provider_cost, 4) < 0;
    }

    /** "Unlimited", "500 MB", "5 GB", "1.5 GB" (1 GB = 1,000 MB, decision D9). */
    public function dataLabel(): string
    {
        if ($this->data_type === self::DATA_UNLIMITED || $this->data_amount_mb === null) {
            return 'Unlimited';
        }

        $mb = (int) $this->data_amount_mb;
        if ($mb < self::MB_PER_GB) {
            return $mb.' MB';
        }

        $gb = rtrim(rtrim(bcdiv((string) $mb, (string) self::MB_PER_GB, 3), '0'), '.');

        return $gb.' GB';
    }

    /** Card title, e.g. "Unlimited - 7 Days" or "5 GB - 7 Days" (existing design format). */
    public function title(): string
    {
        if ($this->display_title) {
            return $this->display_title;
        }

        $days = (int) $this->validity_days;

        return $this->dataLabel().' - '.$days.' '.($days === 1 ? 'Day' : 'Days');
    }
}
