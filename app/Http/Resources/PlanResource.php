<?php

namespace App\Http\Resources;

use App\Models\Country;
use App\Models\Plan;
use App\Services\CatalogueService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public catalogue plan (explicit whitelist — never serialize the model).
 * Never includes provider cost, markup, pricing method, approval status,
 * bundle code or any provider data.
 *
 * @property Plan $resource
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $price = app(CatalogueService::class)->priceData($plan->cataloguePrice);

        return [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'title' => $plan->title(),
            'description' => $plan->description,
            'coverage_type' => $plan->coverage_type,
            'region_name' => $plan->region_name,
            'data_type' => $plan->data_type,
            'data_amount_mb' => $plan->data_amount_mb,
            'data_label' => $plan->dataLabel(),
            'validity_days' => $plan->validity_days,
            'network_speed' => $plan->network_speed,
            'features' => $plan->features ?? [],
            'badge' => $plan->badge,
            'price' => $price,
            'countries' => $plan->countries->map(fn (Country $c) => [
                'iso2' => $c->iso2,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->values()->all(),
        ];
    }
}
