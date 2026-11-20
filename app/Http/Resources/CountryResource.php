<?php

namespace App\Http\Resources;

use App\Models\Country;
use App\Services\CatalogueService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public catalogue country (explicit whitelist — never serialize the model).
 * Wraps one entry of CatalogueService::destinations().
 */
class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Country $country */
        $country = $this->resource['country'];
        $price = app(CatalogueService::class)->priceData($this->resource['cheapest']->cataloguePrice);
        $flag = $country->imagePath('flag');

        return [
            'iso2' => $country->iso2,
            'iso3' => $country->iso3,
            'name' => $country->name,
            'slug' => $country->slug,
            'region' => $country->region,
            'is_popular' => (bool) $country->is_popular,
            'flag_image_url' => $flag ? asset($flag) : null,
            'plans_count' => $this->resource['plans_count'],
            'price_from' => [
                'amount' => $price['amount'],
                'currency' => $price['currency'],
                'formatted' => $price['formatted'],
            ],
        ];
    }
}
