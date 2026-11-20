<?php

namespace App\Http\Requests\Admin;

use App\Models\Country;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Create / edit a country (admin only — the route also requires the "admin" middleware).
 *
 * Form fields: name, ISO2, slug, sort order, active, popular.
 * The iso3, region and flag_image columns still exist (API, existing data) but
 * are no longer edited here: they are not part of validated(), so editing a
 * country never overwrites values already stored in them. The flag is chosen
 * automatically from the ISO2 code (Country::imagePath()).
 */
class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));
        $existing = $this->route('country');

        $this->merge([
            'name' => $name,
            'iso2' => strtoupper(trim((string) $this->input('iso2'))),
            'slug' => $slug !== '' ? Str::lower($slug) : Str::slug($name),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
            // Missing checkbox fields: new countries are active and not popular;
            // an edit without them keeps the current values.
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : ($existing?->is_active ?? true),
            'is_popular' => $this->has('is_popular') ? $this->boolean('is_popular') : ($existing?->is_popular ?? false),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('country')?->getKey();

        return [
            'name' => ['required', 'string', 'max:100', $this->nameIsUnique($id)],
            'iso2' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', Rule::unique('countries', 'iso2')->ignore($id)],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('countries', 'slug')->ignore($id)],
            'sort_order' => ['integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'iso2.unique' => 'A country with this ISO2 code already exists.',
            'iso2.size' => 'The ISO2 code must be exactly two letters, e.g. JP.',
            'iso2.regex' => 'The ISO2 code must be exactly two letters, e.g. JP.',
            'slug.required' => 'Enter a country name or a URL slug.',
            'slug.unique' => 'A country with this URL slug already exists.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'sort_order.integer' => 'The sort order must be a whole number (0 or more).',
            'sort_order.min' => 'The sort order must be a whole number (0 or more).',
        ];
    }

    /** Case-insensitive name uniqueness that behaves the same on MySQL and SQLite. */
    private function nameIsUnique(?int $ignoreId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
            $exists = Country::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower((string) $value)])
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists();

            if ($exists) {
                $fail('A country with this name already exists.');
            }
        };
    }
}
