<?php

namespace Database\Factories;

use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test data only. Defaults to an approved, active manual USD price in effect now.
 *
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    public function definition(): array
    {
        return [
            'currency' => 'USD',
            'pricing_method' => PlanPrice::METHOD_MANUAL,
            'manual_price' => '10.00',
            'calculated_price' => '10.00',
            'calculated_at' => now(),
            'approval_status' => PlanPrice::APPROVAL_APPROVED,
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ];
    }

    public function manual(string $amount): static
    {
        return $this->state(['pricing_method' => PlanPrice::METHOD_MANUAL, 'manual_price' => $amount, 'calculated_price' => $amount]);
    }

    public function pending(): static
    {
        return $this->state(['approval_status' => PlanPrice::APPROVAL_PENDING, 'is_active' => false]);
    }
}
