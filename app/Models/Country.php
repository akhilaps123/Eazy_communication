<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Destination country (ISO 3166-1).
 */
#[Fillable(['iso2', 'iso3', 'name', 'slug', 'region', 'flag_image', 'is_active', 'is_popular', 'sort_order'])]
class Country extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Countries <-> plans through the country_plan table. */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('countries.is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('countries.sort_order')->orderBy('countries.name');
    }

    /**
     * Existing website image for this country (decision D8: no image columns).
     *
     * @param  'flag'|'sphere'|'card'|'banner'  $kind
     * @return string|null  path relative to /public, for asset()
     */
    public function imagePath(string $kind): ?string
    {
        if ($kind === 'flag' && $this->flag_image) {
            return $this->flag_image;
        }

        $mapped = config('catalogue.country_images.'.strtoupper((string) $this->iso2).'.'.$kind);

        return $mapped ?? match ($kind) {
            'sphere' => $this->imagePath('flag'),
            'card', 'banner' => config('catalogue.fallback_images.'.$kind),
            default => null,
        };
    }

    /**
     * Flag images that already exist in public/assets/images (choices for the admin form).
     *
     * @return list<string>
     */
    public static function availableFlagImages(): array
    {
        $files = glob(public_path('assets/images/flag-*')) ?: [];

        return collect($files)
            ->map(fn (string $file) => 'assets/images/'.basename($file))
            ->sort()
            ->values()
            ->all();
    }
}
