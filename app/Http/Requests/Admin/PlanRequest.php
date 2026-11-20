<?php

namespace App\Http\Requests\Admin;

use App\Models\Country;
use App\Models\Plan;
use App\Models\ProviderBundle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit a plan.
 *
 * Provider bundle (Phase 9B-1):
 * - Create: the admin selects an EXISTING provider bundle (provider_bundle_id).
 *   No bundle is created here; cost and currency come from that bundle.
 * - Edit: the linked bundle is not changed by this form (use "Change provider
 *   bundle"). Only a manually entered bundle (never synced from eSIM Go) keeps
 *   its editable cost / currency / availability, as before. Synced bundles
 *   are provider-owned and read-only.
 *
 * Rules: local = exactly 1 country, regional = 2 or more, global = 1 or more;
 * fixed data needs an amount (GB or MB, stored as whole MB, 1 GB = 1,000 MB).
 * Activation is a separate action (see PlanController::activate).
 */
class PlanRequest extends FormRequest
{
    public const MAX_FEATURES = 10;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => $slug !== '' ? Str::lower($slug) : Str::slug($name),
            'provider_cost' => trim((string) $this->input('provider_cost')),
            'provider_currency' => strtoupper(trim((string) $this->input('provider_currency'))),
            'data_amount' => trim((string) $this->input('data_amount')) ?: null,
            'data_unit' => strtoupper((string) $this->input('data_unit', 'GB')),
            'bundle_available' => $this->boolean('bundle_available'),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
            'badge' => $this->input('badge') ?: null,
            'countries' => array_values(array_filter((array) $this->input('countries', []), fn ($id) => $id !== null && $id !== '')),
        ]);
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            ...$this->bundleRules($plan),
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('plans', 'slug')->ignore($plan?->getKey())],
            'display_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'coverage_type' => ['required', Rule::in(Plan::COVERAGE_TYPES)],
            'region_name' => ['nullable', 'string', 'max:100'],
            'data_type' => ['required', Rule::in(Plan::DATA_TYPES)],
            'data_amount' => ['nullable', 'required_if:data_type,'.Plan::DATA_FIXED, 'regex:/^\d{1,7}(\.\d{1,3})?$/'],
            'data_unit' => ['required_if:data_type,'.Plan::DATA_FIXED, Rule::in(['GB', 'MB'])],
            'validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'network_speed' => ['nullable', 'string', 'max:50'],
            'features' => ['nullable', 'string', 'max:2000'],
            'badge' => ['nullable', Rule::in(array_keys(Plan::BADGES))],
            'sort_order' => ['integer', 'min:0', 'max:1000000'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => ['integer', 'distinct', Rule::exists('countries', 'id')],
        ];
    }

    /** Provider bundle rules — never shown to customers. */
    private function bundleRules(?Plan $plan): array
    {
        if ($plan === null) {
            return [
                'provider_bundle_id' => [
                    'required', 'integer',
                    Rule::exists('provider_bundles', 'id')->where('provider', ProviderBundle::PROVIDER_ESIMGO),
                    Rule::unique('plans', 'provider_bundle_id'),   // one plan per bundle (existing unique key)
                ],
            ];
        }

        if ($this->editsManualBundle()) {
            return [
                'provider_cost' => ['required', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
                'provider_currency' => ['required', Rule::in(self::providerCurrencies($plan))],
                'bundle_available' => ['boolean'],
            ];
        }

        return [];
    }

    /** Editing a plan whose bundle was entered by hand (never synced from eSIM Go)? */
    public function editsManualBundle(): bool
    {
        $bundle = $this->route('plan')?->providerBundle;

        return $bundle !== null && ! $bundle->isSynced();
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Another plan already uses this URL slug.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'data_amount.required_if' => 'Fixed data plans need a data amount.',
            'data_amount.regex' => 'Enter the data amount as a number, e.g. 5 or 1.5.',
            'countries.required' => 'Select at least one country.',
            'countries.*.distinct' => 'A country was selected more than once.',
            'countries.*.exists' => 'One of the selected countries does not exist.',
            'provider_bundle_id.required' => 'Select a provider bundle.',
            'provider_bundle_id.integer' => 'Select a provider bundle from the list.',
            'provider_bundle_id.exists' => 'The selected provider bundle does not exist.',
            'provider_bundle_id.unique' => 'This provider bundle is already linked to another plan.',
            'provider_cost.required' => 'The provider cost is required.',
            'provider_cost.regex' => 'Enter the provider cost as a number with up to 4 decimals, e.g. 4.5.',
            'provider_currency.required' => 'Choose the provider currency.',
            'provider_currency.in' => 'Choose the provider currency from the list.',
        ];
    }

    /**
     * Currencies offered in the dropdown (config/catalogue.php), plus the
     * bundle's current currency when editing, so an existing value is kept
     * even if it is not in the standard list.
     *
     * @return list<string>
     */
    public static function providerCurrencies(?Plan $plan = null): array
    {
        $currencies = array_map('strtoupper', (array) config('catalogue.provider_currencies', ['USD']));
        $current = $plan?->providerBundle?->provider_currency;

        if ($current && ! in_array(strtoupper($current), $currencies, true)) {
            $currencies[] = strtoupper($current);
        }

        return array_values(array_unique($currencies));
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $countryIds = array_map('intval', $this->input('countries', []));
            $count = count($countryIds);

            match ($this->input('coverage_type')) {
                Plan::COVERAGE_LOCAL => $count === 1 ?: $validator->errors()->add('countries', 'A local plan must cover exactly one country.'),
                Plan::COVERAGE_REGIONAL => $count >= 2 ?: $validator->errors()->add('countries', 'A regional plan must cover at least two countries.'),
                default => null,
            };

            // New links may only use active countries; a country already linked to
            // this plan may stay linked even if it was deactivated later.
            $alreadyLinked = $this->route('plan')?->countries()->pluck('countries.id')->all() ?? [];
            $inactive = Country::query()->whereKey($countryIds)->where('is_active', false)
                ->whereKeyNot($alreadyLinked)->pluck('name');
            if ($inactive->isNotEmpty()) {
                $validator->errors()->add('countries', 'Inactive countries cannot be added: '.$inactive->join(', ').'.');
            }

            // A new plan may only use a bundle that eSIM Go is still selling.
            if ($this->route('plan') === null
                && ! ProviderBundle::query()->whereKey($this->integer('provider_bundle_id'))->value('is_available')) {
                $validator->errors()->add('provider_bundle_id', 'This provider bundle is not available from eSIM Go and cannot be used for a new plan.');
            }

            if ($this->input('data_type') === Plan::DATA_FIXED && $this->dataAmountMb() === null) {
                $validator->errors()->add('data_amount', 'The data amount must be a whole number of MB greater than zero (1 GB = 1,000 MB).');
            }
        }];
    }

    /** Data amount converted to whole MB with bcmath (1 GB = 1,000 MB), or null if invalid / unlimited. */
    public function dataAmountMb(): ?int
    {
        if ($this->input('data_type') !== Plan::DATA_FIXED || $this->input('data_amount') === null) {
            return null;
        }

        $factor = $this->input('data_unit') === 'GB' ? (string) Plan::MB_PER_GB : '1';
        $mb = bcmul((string) $this->input('data_amount'), $factor, 3);

        if (bccomp($mb, bcadd($mb, '0', 0), 3) !== 0) {
            return null;   // fractions of a MB are not allowed
        }
        if (bccomp($mb, '1', 0) < 0 || bccomp($mb, '4294967295', 0) > 0) {
            return null;   // must fit the unsigned INT column
        }

        return (int) bcadd($mb, '0', 0);
    }

    /** One feature per line -> JSON list. */
    public function featureList(): ?array
    {
        $features = collect(preg_split('/\R/', (string) $this->input('features')))
            ->map(fn ($line) => Str::limit(trim($line), 100, ''))
            ->filter()
            ->unique()
            ->take(self::MAX_FEATURES)
            ->values()
            ->all();

        return $features ?: null;
    }
}
