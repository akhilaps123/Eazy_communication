<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only.
 *
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'provider_bundle_id' => ProviderBundleFactory::new(),
            'slug' => 'test-plan-'.$n,
            'name' => 'Test plan '.$n,
            'coverage_type' => Plan::COVERAGE_LOCAL,
            'data_type' => Plan::DATA_FIXED,
            'data_amount_mb' => 5000,
            'validity_days' => 7,
            'network_speed' => '5G',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(['data_type' => Plan::DATA_UNLIMITED, 'data_amount_mb' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
