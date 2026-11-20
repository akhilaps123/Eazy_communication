<?php

namespace App\Models;

use App\Services\EsimGo\CountryMapper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A bundle from the eSIM Go catalogue (wholesale data — never shown to
 * customers directly; customer-facing data lives in Plan).
 */
#[Fillable([
    'provider', 'bundle_code', 'name', 'description', 'data_amount_mb', 'is_unlimited',
    'duration_days', 'country_codes', 'speed', 'provider_cost', 'provider_currency',
    'is_available', 'raw_payload', 'last_synced_at',
])]
class ProviderBundle extends Model
{
    public const PROVIDER_ESIMGO = 'esimgo';

    protected function casts(): array
    {
        return [
            'data_amount_mb' => 'integer',
            'is_unlimited' => 'boolean',
            'duration_days' => 'integer',
            'country_codes' => 'array',
            'provider_cost' => 'decimal:4',
            'is_available' => 'boolean',
            'raw_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /** One bundle is published as at most one plan (ER: one-to-zero-or-one). */
    /** Plans reference a bundle through plans.provider_bundle_id (unique: one plan per bundle). */
    public function plan(): HasOne
    {
        return $this->hasOne(Plan::class);
    }

    /**
     * Synced = written by the eSIM Go catalogue sync (provider-owned data, read-only
     * in the admin). Manual = entered by hand in the plan form before Phase 9B.
     */
    public function isSynced(): bool
    {
        return $this->last_synced_at !== null;
    }

    /** Text search on the bundle code, name and provider description. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.$term.'%';   // same LIKE search as the plan list (works on MySQL and SQLite)

        return $query->where(fn (Builder $q) => $q
            ->where('bundle_code', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orWhere('description', 'like', $like));
    }

    /** Bundles that include a country (ISO 3166-1 alpha-2), as stored by the sync. */
    public function scopeForCountry(Builder $query, ?string $iso2): Builder
    {
        $iso2 = strtoupper(trim((string) $iso2));

        return preg_match('/^[A-Z]{2}$/', $iso2)
            ? $query->where('country_codes', 'like', '%"'.$iso2.'"%')
            : $query;
    }

    /** Bundles an admin may link to a plan: eSIM Go, available, not linked to another plan. */
    public function scopeSelectableForPlan(Builder $query, ?Plan $plan = null): Builder
    {
        return $query->where('provider', self::PROVIDER_ESIMGO)
            ->where('is_available', true)
            ->whereDoesntHave('plan', fn (Builder $q) => $q->when($plan?->exists, fn ($p) => $p->whereKeyNot($plan->getKey())));
    }

    /** "Unlimited", "500 MB", "5 GB", "1.5 GB", or "—" when the provider gave no amount. */
    public function dataLabel(): string
    {
        if ($this->is_unlimited) {
            return 'Unlimited';
        }
        if ($this->data_amount_mb === null) {
            return '—';
        }

        $mb = (int) $this->data_amount_mb;
        if ($mb < Plan::MB_PER_GB) {
            return $mb.' MB';
        }

        return rtrim(rtrim(bcdiv((string) $mb, (string) Plan::MB_PER_GB, 3), '0'), '.').' GB';
    }

    public function validityLabel(): string
    {
        return $this->duration_days === null ? '—' : $this->duration_days.' '.($this->duration_days === 1 ? 'day' : 'days');
    }

    /** "4.5000 USD" style cost, or "—" when the provider gave no price. */
    public function costLabel(): string
    {
        return $this->provider_cost === null ? '—' : rtrim(rtrim((string) $this->provider_cost, '0'), '.').' '.$this->provider_currency;
    }

    /**
     * How the provider's own location data maps to countries (from raw_payload),
     * e.g. regions resolved through roamingEnabled and identifiers that could not
     * be mapped (shown to admins for review).
     *
     * @return array{codes: list<string>, resolved: list<string>, unmapped: list<string>, regional: bool, roaming_added: int}
     */
    public function locationMapping(): array
    {
        return CountryMapper::map((array) $this->raw_payload);
    }

    /** @return list<string> */
    public function countryList(): array
    {
        return array_values(array_filter((array) $this->country_codes, 'is_string'));
    }
}
