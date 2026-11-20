<?php

namespace Database\Factories;

use App\Models\ProviderBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Bundle codes are fake ("TEST_..."), not real eSIM Go bundles.
 *
 * @extends Factory<ProviderBundle>
 */
class ProviderBundleFactory extends Factory
{
    protected $model = ProviderBundle::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'provider' => ProviderBundle::PROVIDER_ESIMGO,
            'bundle_code' => 'TEST_BUNDLE_'.$n,
            'name' => 'TEST_BUNDLE_'.$n,
            'data_amount_mb' => 5000,
            'is_unlimited' => false,
            'duration_days' => 7,
            'provider_cost' => '5.0000',
            'provider_currency' => 'USD',
            'is_available' => true,
        ];
    }

    public function cost(string $amount, string $currency = 'USD'): static
    {
        return $this->state(['provider_cost' => $amount, 'provider_currency' => $currency]);
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }
}
