<?php

namespace Tests\Feature\Database;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\ProviderBundle;
use DomainException;

/**
 * Pricing rules from planning report section D. All amounts are arbitrary
 * test numbers in the ISO "XXX" test currency, not real business prices.
 */
class PlanPricingTest extends DatabaseTestCase
{
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $bundle = ProviderBundle::create([
            'provider' => 'esimgo', 'bundle_code' => 'TEST_BUNDLE', 'name' => 'Test bundle',
            'provider_cost' => '10.0000', 'provider_currency' => 'XXX',
        ]);
        $this->plan = Plan::create([
            'provider_bundle_id' => $bundle->id, 'slug' => 'test-plan', 'name' => 'Test plan',
            'coverage_type' => 'local', 'data_type' => 'fixed', 'data_amount_mb' => 1024, 'validity_days' => 7,
        ]);
    }

    private function price(array $attributes): PlanPrice
    {
        // Caller's attributes win; these are only defaults.
        return new PlanPrice($attributes + ['plan_id' => $this->plan->id, 'currency' => 'XXX', 'effective_from' => now()]);
    }

    public function test_manual_price_is_used_as_entered(): void
    {
        $price = $this->price(['pricing_method' => 'manual', 'manual_price' => '15.50']);

        $this->assertSame('15.50', $price->calculateSellingPrice());
    }

    public function test_percentage_markup_is_applied_to_provider_cost(): void
    {
        $price = $this->price(['pricing_method' => 'markup_percent', 'markup_value' => '25']);

        $this->assertSame('12.50', $price->calculateSellingPrice());   // 10 x 1.25
    }

    public function test_fixed_markup_is_added_to_provider_cost(): void
    {
        $price = $this->price(['pricing_method' => 'markup_fixed', 'markup_value' => '3.25']);

        $this->assertSame('13.25', $price->calculateSellingPrice());   // 10 + 3.25
    }

    public function test_results_are_rounded_half_up_to_two_decimals(): void
    {
        $this->assertSame('11.24', $this->price(['pricing_method' => 'markup_percent', 'markup_value' => '12.444'])->calculateSellingPrice());
        $this->assertSame('11.25', $this->price(['pricing_method' => 'markup_percent', 'markup_value' => '12.45'])->calculateSellingPrice());
    }

    public function test_markup_is_refused_when_currencies_differ(): void
    {
        $this->expectException(DomainException::class);

        (new PlanPrice([
            'plan_id' => $this->plan->id, 'currency' => 'XTS', 'pricing_method' => 'markup_fixed',
            'markup_value' => '1', 'effective_from' => now(),
        ]))->calculateSellingPrice();
    }

    public function test_price_below_provider_cost_cannot_be_saved(): void
    {
        $this->expectException(DomainException::class);

        $this->price(['pricing_method' => 'manual', 'manual_price' => '9.99'])->refreshCalculatedPrice()->save();
    }

    public function test_stale_calculated_price_cannot_hide_a_below_cost_manual_price(): void
    {
        $this->expectException(DomainException::class);

        $this->price(['pricing_method' => 'manual', 'manual_price' => '9.00', 'calculated_price' => '20.00'])->save();
    }

    public function test_price_at_or_above_cost_can_be_saved(): void
    {
        $price = $this->price(['pricing_method' => 'markup_percent', 'markup_value' => '0'])->refreshCalculatedPrice();
        $price->save();

        $this->assertSame('10.00', $price->fresh()->calculated_price);
        $this->assertNotNull($price->calculated_at);
    }

    public function test_new_prices_default_to_inactive_and_pending_approval(): void
    {
        $price = $this->price(['pricing_method' => 'manual', 'manual_price' => '20.00']);
        $price->save();
        $price->refresh();

        $this->assertFalse($price->is_active);
        $this->assertSame('pending', $price->approval_status);
        $this->assertNull($this->plan->currentPrice('XXX'));
    }

    public function test_current_price_picks_the_latest_applicable_row_and_keeps_history(): void
    {
        $approved = ['is_active' => true, 'approval_status' => 'approved'];

        $this->price(['pricing_method' => 'manual', 'manual_price' => '20.00', 'calculated_price' => '20.00',
            'effective_from' => now()->subDays(10)] + $approved)->save();
        $this->price(['pricing_method' => 'manual', 'manual_price' => '18.00', 'calculated_price' => '18.00',
            'effective_from' => now()->subDay()] + $approved)->save();
        $this->price(['pricing_method' => 'manual', 'manual_price' => '25.00', 'calculated_price' => '25.00',
            'effective_from' => now()->addDay()] + $approved)->save();                               // future
        $this->price(['pricing_method' => 'manual', 'manual_price' => '30.00', 'calculated_price' => '30.00',
            'effective_from' => now()->subHour(), 'is_active' => true])->save();                     // not approved
        $this->price(['pricing_method' => 'manual', 'manual_price' => '15.00', 'calculated_price' => '15.00',
            'effective_from' => now()->subDays(5), 'effective_to' => now()->subDays(2)] + $approved)->save(); // expired

        $this->assertSame('18.00', $this->plan->currentPrice('xxx')->calculated_price);
        $this->assertSame('25.00', $this->plan->currentPrice('XXX', now()->addDays(2))->calculated_price);
        $this->assertNull($this->plan->currentPrice('XTS'));
        $this->assertCount(5, $this->plan->prices);
    }
}
