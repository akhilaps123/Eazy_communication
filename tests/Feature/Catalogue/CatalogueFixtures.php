<?php

namespace Tests\Feature\Catalogue;

use App\Models\Country;
use App\Models\Plan;
use App\Models\User;
use Database\Factories\CountryFactory;
use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Database\Factories\ProviderBundleFactory;

/** Test-only helpers (in-memory database). */
trait CatalogueFixtures
{
    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $admin;
    }

    protected function country(array $attributes = []): Country
    {
        return CountryFactory::new()->create($attributes);
    }

    /**
     * A plan that meets every sellable rule: active, bundle available (cost 5.00 USD),
     * one active country, approved active USD manual price.
     */
    protected function sellablePlan(array $plan = [], ?Country $country = null, string $price = '10.00', array $priceState = [], array $bundle = []): Plan
    {
        $country ??= $this->country();
        $model = PlanFactory::new()
            ->state(['provider_bundle_id' => ProviderBundleFactory::new()->state($bundle)])
            ->create($plan);
        $model->countries()->attach($country);
        PlanPriceFactory::new()->manual($price)->create(['plan_id' => $model->id] + $priceState);

        return $model->fresh();
    }

    /** A plan with bundle and country but no price, inactive (as created in the admin panel). */
    protected function draftPlan(array $plan = [], ?Country $country = null, array $bundle = []): Plan
    {
        $model = PlanFactory::new()->inactive()
            ->state(['provider_bundle_id' => ProviderBundleFactory::new()->state($bundle)])
            ->create($plan);
        $model->countries()->attach($country ?? $this->country());

        return $model->fresh();
    }

    protected function enableLiveCatalogue(): void
    {
        config(['catalogue.live' => true]);
    }
}
