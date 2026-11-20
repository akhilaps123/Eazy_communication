<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\ProviderBundle;
use App\Services\CatalogueService;
use DomainException;

/**
 * Read-only helpers for the ADMIN plan pages (never used by customer pages or
 * the public API — provider cost and margin stay internal).
 *
 * Nothing here changes data or business rules: it only explains them.
 */
class PlanAdminInsights
{
    /**
     * Why customers cannot buy this plan, using relations already loaded by the
     * plans list (providerBundle, countries, and prices limited to applicable
     * display-currency rows, newest first) — so no extra query per row.
     * Mirrors CatalogueService's sellable rules.
     *
     * @return list<string>
     */
    public static function hiddenReasons(Plan $plan): array
    {
        $reasons = [];

        if (! $plan->providerBundle?->is_available) {
            $reasons[] = 'Bundle not available from eSIM Go';
        }
        if (! $plan->countries->contains(fn ($country) => $country->is_active)) {
            $reasons[] = 'No active country';
        }

        $price = $plan->prices->first();
        if ($price === null) {
            $reasons[] = 'No approved current price';
        } elseif ($plan->isPriceBelowCost($price)) {
            $reasons[] = 'Current price is below provider cost';
        }

        return $reasons;
    }

    /**
     * History label for a price row.
     *
     * @return array{label: string, class: string}
     */
    public static function priceStatus(PlanPrice $price, ?int $currentPriceId): array
    {
        return match (true) {
            $price->approval_status === PlanPrice::APPROVAL_PENDING => ['label' => 'Pending', 'class' => 'adm-badge-warn'],
            $price->approval_status === PlanPrice::APPROVAL_REJECTED => ['label' => 'Rejected', 'class' => 'adm-badge-error'],
            ! $price->is_active => ['label' => 'Deactivated', 'class' => ''],
            $price->effective_to !== null && $price->effective_to->isPast() => ['label' => 'Ended', 'class' => ''],
            $price->id === $currentPriceId => ['label' => 'In effect', 'class' => 'adm-badge-ok'],
            $price->effective_from->isFuture() => ['label' => 'Scheduled', 'class' => 'adm-badge-info'],
            default => ['label' => 'Superseded', 'class' => ''],
        };
    }

    /** Short description of the pricing rule, e.g. "Cost + 25%". */
    public static function ruleLabel(PlanPrice $price): string
    {
        $value = $price->markup_value !== null ? rtrim(rtrim((string) $price->markup_value, '0'), '.') : '';

        return match ($price->pricing_method) {
            PlanPrice::METHOD_MANUAL => 'Manual price',
            PlanPrice::METHOD_MARKUP_PERCENT => 'Cost + '.$value.'%',
            PlanPrice::METHOD_MARKUP_FIXED => 'Cost + '.$value.' '.$price->currency,
            default => (string) $price->pricing_method,
        };
    }

    /** The amount the customer would pay for this price row. */
    public static function amount(PlanPrice $price): string
    {
        return PlanPrice::roundMoney((string) ($price->calculated_price ?? $price->manual_price ?? '0'));
    }

    /** "$4.50", or "$3.3333" when the cost has more than 2 decimals (no float maths). */
    public static function cost(ProviderBundle $bundle): string
    {
        $cost = (string) $bundle->provider_cost;
        [$whole, $fraction] = array_pad(explode('.', $cost, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $fraction = strlen($fraction) < 2 ? str_pad($fraction, 2, '0') : $fraction;
        $symbol = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'][strtoupper((string) $bundle->provider_currency)] ?? '';

        return $symbol !== '' ? $symbol.$whole.'.'.$fraction : $whole.'.'.$fraction.' '.$bundle->provider_currency;
    }

    /**
     * Admin-only margin when cost and selling price use the same currency.
     * Margin % is the share of the selling price (e.g. $1.49 of $11.49 = 13%).
     *
     * @return array{cost: string, price: string, margin: string, percent: string}|null
     */
    public static function margin(string $amount, string $currency, ?ProviderBundle $bundle): ?array
    {
        if ($bundle === null || $bundle->provider_cost === null
            || strtoupper((string) $bundle->provider_currency) !== strtoupper($currency)) {
            return null;
        }

        $margin = bcsub($amount, (string) $bundle->provider_cost, 4);
        $percent = bccomp($amount, '0', 2) > 0
            ? self::roundHalfUp(bcdiv(bcmul($margin, '100', 6), $amount, 6), 0)
            : '0';

        return [
            'cost' => self::cost($bundle),
            'price' => CatalogueService::money($amount, $currency),
            'margin' => CatalogueService::money(self::roundHalfUp($margin, 2), $currency),
            'percent' => $percent,
        ];
    }

    /**
     * For an approved markup price: the amount it would have with the CURRENT
     * provider cost, when that differs from the stored amount (null = up to date).
     */
    public static function staleMarkupAmount(PlanPrice $price, Plan $plan): ?string
    {
        if ($price->pricing_method === PlanPrice::METHOD_MANUAL || $price->approval_status !== PlanPrice::APPROVAL_APPROVED) {
            return null;
        }

        $copy = clone $price;
        $copy->setRelation('plan', $plan);

        try {
            $now = $copy->calculateSellingPrice();
        } catch (DomainException) {
            return 'unavailable';   // e.g. the provider currency changed — cannot be recalculated
        }

        return bccomp($now, self::amount($price), 2) === 0 ? null : CatalogueService::money($now, $price->currency);
    }

    /** Half-up rounding of a decimal string (positive or negative), no floats. */
    private static function roundHalfUp(string $value, int $scale): string
    {
        $offset = '0.'.str_repeat('0', $scale).'5';
        $offset = bccomp($value, '0', 10) < 0 ? '-'.$offset : $offset;

        return bcadd($value, $offset, $scale);
    }
}
