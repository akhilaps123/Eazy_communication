<?php

namespace App\Models;

use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A selling-price rule for a plan in one currency (planning report section D).
 *
 *   manual          -> manual_price
 *   markup_percent  -> provider cost x (1 + markup_value / 100)
 *   markup_fixed    -> provider cost + markup_value
 *
 * Arithmetic uses bcmath on decimal strings (never floats) and rounds
 * half-up to 2 decimals. Marketing rounding (e.g. ".99") and currency
 * conversion are NOT applied: both are pending decisions (report J5, J11).
 *
 * A price may never be saved below the provider cost (checked whenever the
 * price and the provider cost are in the same currency).
 */
#[Fillable([
    'plan_id', 'currency', 'pricing_method', 'manual_price', 'markup_value', 'compare_at_price',
    'calculated_price', 'calculated_at', 'approval_status', 'is_active', 'effective_from',
    'effective_to', 'created_by', 'approved_by',
])]
class PlanPrice extends Model
{
    public const METHOD_MANUAL = 'manual';

    public const METHOD_MARKUP_PERCENT = 'markup_percent';

    public const METHOD_MARKUP_FIXED = 'markup_fixed';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'manual_price' => 'decimal:2',
            'markup_value' => 'decimal:4',
            'compare_at_price' => 'decimal:2',
            'calculated_price' => 'decimal:2',
            'calculated_at' => 'datetime',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PlanPrice $price) {
            $price->currency = strtoupper((string) $price->currency);
            $price->assertNotBelowProviderCost();
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Active + approved + inside its effective period at the given moment (default: now). */
    public function scopeApplicable(Builder $query, ?DateTimeInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where('approval_status', self::APPROVAL_APPROVED)
            ->where('effective_from', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at));
    }

    /**
     * Calculate the selling price from this rule. Returns a decimal string (e.g. "39.99").
     *
     * @throws DomainException when the rule cannot be applied safely
     */
    public function calculateSellingPrice(): string
    {
        if ($this->pricing_method === self::METHOD_MANUAL) {
            if ($this->manual_price === null) {
                throw new DomainException('Manual pricing requires a manual price.');
            }

            return self::roundMoney((string) $this->manual_price);
        }

        if (! in_array($this->pricing_method, [self::METHOD_MARKUP_PERCENT, self::METHOD_MARKUP_FIXED], true)) {
            throw new DomainException("Unknown pricing method [{$this->pricing_method}].");
        }
        if ($this->markup_value === null) {
            throw new DomainException('Markup pricing requires a markup value.');
        }

        $bundle = $this->plan?->providerBundle;
        if ($bundle === null || $bundle->provider_cost === null) {
            throw new DomainException('Provider cost is unknown, so a markup price cannot be calculated.');
        }
        if (strtoupper((string) $bundle->provider_currency) !== strtoupper((string) $this->currency)) {
            throw new DomainException('Provider cost and selling price use different currencies; currency conversion rules are not decided yet.');
        }

        $cost = (string) $bundle->provider_cost;
        $markup = (string) $this->markup_value;

        $raw = $this->pricing_method === self::METHOD_MARKUP_PERCENT
            ? bcmul($cost, bcadd('1', bcdiv($markup, '100', 10), 10), 10)
            : bcadd($cost, $markup, 10);

        return self::roundMoney($raw);
    }

    /** Store the calculated price (does not save the model). */
    public function refreshCalculatedPrice(): static
    {
        $this->calculated_price = $this->calculateSellingPrice();
        $this->calculated_at = now();

        return $this;
    }

    /**
     * Throws if the stored selling price is lower than the provider cost.
     * Only comparable when both amounts are in the same currency.
     */
    public function assertNotBelowProviderCost(): void
    {
        // Every amount that can be charged: the stored result, and for manual pricing the manual price itself.
        $amounts = array_filter([
            $this->calculated_price,
            $this->pricing_method === self::METHOD_MANUAL ? $this->manual_price : null,
        ], fn ($amount) => $amount !== null);

        if ($amounts === [] || $this->plan_id === null) {
            return;
        }

        $bundle = Plan::query()->with('providerBundle')->find($this->plan_id)?->providerBundle;
        if ($bundle === null || $bundle->provider_cost === null) {
            return;
        }
        if (strtoupper((string) $bundle->provider_currency) !== strtoupper((string) $this->currency)) {
            return; // different currencies — needs the (pending) conversion rules to compare
        }

        foreach ($amounts as $amount) {
            if (bccomp((string) $amount, (string) $bundle->provider_cost, 4) < 0) {
                throw new DomainException(sprintf(
                    'Selling price %s %s is below the provider cost %s %s.',
                    $amount, $this->currency, $bundle->provider_cost, $bundle->provider_currency,
                ));
            }
        }
    }

    /** Round a positive decimal string half-up to 2 decimal places. */
    public static function roundMoney(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
