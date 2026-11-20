<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Database\Factories\PlanPriceFactory;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class PlanPriceManagementTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private User $adminUser;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = $this->admin();
        $this->actingAs($this->adminUser);
        $this->plan = $this->draftPlan(bundle: ['provider_cost' => '4.0000', 'provider_currency' => 'USD']);
    }

    private function store(array $data)
    {
        return $this->from("/admin/plans/{$this->plan->id}")->post("/admin/plans/{$this->plan->id}/prices", $data + [
            'effective_from' => now()->subMinute()->format('Y-m-d\TH:i'),
            'intent' => 'save',
        ]);
    }

    public function test_preview_shows_the_calculated_price_without_saving(): void
    {
        $this->store(['pricing_method' => 'markup_percent', 'markup_value' => '25', 'intent' => 'preview'])
            ->assertRedirect("/admin/plans/{$this->plan->id}")
            ->assertSessionHas('price_preview', fn ($p) => $p['amount'] === '5.00' && $p['formatted'] === '$5.00');

        $this->assertDatabaseCount('plan_prices', 0);
    }

    public function test_new_prices_are_pending_inactive_usd_and_server_controlled(): void
    {
        $this->store([
            'pricing_method' => 'manual', 'manual_price' => '9.99', 'compare_at_price' => '12.99',
            // attempts to set server-controlled fields are ignored:
            'currency' => 'EUR', 'approval_status' => 'approved', 'is_active' => '1',
            'created_by' => 999, 'approved_by' => 999, 'calculated_price' => '0.01',
        ])->assertSessionHasNoErrors();

        $price = PlanPrice::firstOrFail();
        $this->assertSame(['USD', 'pending', false, $this->adminUser->id, null, '9.99', '9.99'], [
            $price->currency, $price->approval_status, $price->is_active, $price->created_by, $price->approved_by,
            $price->manual_price, $price->calculated_price,
        ]);
    }

    public function test_markup_calculations_use_decimal_arithmetic(): void
    {
        $this->store(['pricing_method' => 'markup_percent', 'markup_value' => '25'])->assertSessionHasNoErrors();
        $this->store(['pricing_method' => 'markup_fixed', 'markup_value' => '1.5', 'effective_from' => now()->subMinutes(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        $this->assertSame(['5.00', '5.50'], PlanPrice::orderBy('id')->pluck('calculated_price')->all());

        // 3.3333 + 10 % = 3.66663 -> rounded half-up to 3.67 (no float error)
        $this->plan->providerBundle->update(['provider_cost' => '3.3333']);
        $this->store(['pricing_method' => 'markup_percent', 'markup_value' => '10', 'intent' => 'preview'])
            ->assertSessionHas('price_preview', fn ($p) => $p['amount'] === '3.67');
    }

    public function test_markup_is_blocked_when_provider_currency_is_not_usd_but_manual_usd_is_allowed(): void
    {
        $this->plan->providerBundle->update(['provider_currency' => 'EUR']);

        $this->store(['pricing_method' => 'markup_percent', 'markup_value' => '25'])->assertSessionHasErrors('pricing_method');
        $this->store(['pricing_method' => 'markup_fixed', 'markup_value' => '2'])->assertSessionHasErrors('pricing_method');
        $this->assertDatabaseCount('plan_prices', 0);

        // Manual USD price is allowed; cost is in EUR so it cannot be compared (no conversion).
        $this->store(['pricing_method' => 'manual', 'manual_price' => '1.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('plan_prices', 1);
    }

    public function test_prices_below_provider_cost_are_rejected_when_currencies_match(): void
    {
        $this->store(['pricing_method' => 'manual', 'manual_price' => '3.99'])->assertSessionHasErrors('pricing_method');
        $this->store(['pricing_method' => 'manual', 'manual_price' => '0'])->assertSessionHasErrors('manual_price');
        $this->assertDatabaseCount('plan_prices', 0);

        $this->store(['pricing_method' => 'manual', 'manual_price' => '4.00'])->assertSessionHasNoErrors();   // equal to cost is allowed
    }

    public function test_field_validation(): void
    {
        $this->store([])->assertSessionHasErrors('pricing_method');
        $this->store(['pricing_method' => 'manual'])->assertSessionHasErrors('manual_price');
        $this->store(['pricing_method' => 'markup_percent'])->assertSessionHasErrors('markup_value');
        $this->store(['pricing_method' => 'manual', 'manual_price' => '9.999'])->assertSessionHasErrors('manual_price');
        $this->store(['pricing_method' => 'manual', 'manual_price' => '9.99', 'compare_at_price' => '9.99'])->assertSessionHasErrors('compare_at_price');
        $this->store(['pricing_method' => 'manual', 'manual_price' => '9.99', 'effective_to' => now()->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('effective_to');
        $this->assertDatabaseCount('plan_prices', 0);
    }

    public function test_two_prices_cannot_start_at_the_same_moment(): void
    {
        $start = now()->subMinute()->format('Y-m-d\TH:i');
        $this->store(['pricing_method' => 'manual', 'manual_price' => '9.99', 'effective_from' => $start])->assertSessionHasNoErrors();
        $this->store(['pricing_method' => 'manual', 'manual_price' => '8.99', 'effective_from' => $start])->assertSessionHasErrors('effective_from');
    }

    public function test_admin_can_approve_their_own_price(): void
    {
        $this->store(['pricing_method' => 'manual', 'manual_price' => '9.99']);
        $price = PlanPrice::firstOrFail();

        $this->post("/admin/plans/{$this->plan->id}/prices/{$price->id}/approve")->assertSessionHasNoErrors();

        $price->refresh();
        $this->assertSame(['approved', true, $this->adminUser->id, $this->adminUser->id], [
            $price->approval_status, $price->is_active, $price->created_by, $price->approved_by,
        ]);
        $this->assertSame($price->id, $this->plan->currentPrice('USD')?->id);
    }

    public function test_approval_recalculates_and_blocks_a_price_that_is_now_below_cost(): void
    {
        $this->store(['pricing_method' => 'manual', 'manual_price' => '5.00']);
        $price = PlanPrice::firstOrFail();
        $this->plan->providerBundle->update(['provider_cost' => '6.0000']);   // cost rose after the price was entered

        $this->post("/admin/plans/{$this->plan->id}/prices/{$price->id}/approve")->assertSessionHasErrors('price');
        $this->assertSame('pending', $price->fresh()->approval_status);
    }

    public function test_pending_prices_can_be_edited_but_approved_prices_cannot(): void
    {
        $this->store(['pricing_method' => 'manual', 'manual_price' => '9.99']);
        $price = PlanPrice::firstOrFail();
        $edit = "/admin/plans/{$this->plan->id}/prices/{$price->id}";

        $this->get($edit.'/edit')->assertOk();
        $this->put($edit, ['pricing_method' => 'manual', 'manual_price' => '7.49', 'effective_from' => $price->effective_from->format('Y-m-d\TH:i'), 'intent' => 'save'])
            ->assertSessionHasNoErrors();
        $this->assertSame('7.49', $price->fresh()->calculated_price);

        $this->post($edit.'/approve');

        $this->get($edit.'/edit')->assertRedirect("/admin/plans/{$this->plan->id}")->assertSessionHasErrors('price');
        $this->put($edit, ['pricing_method' => 'manual', 'manual_price' => '4.50', 'effective_from' => $price->effective_from->format('Y-m-d\TH:i'), 'intent' => 'save'])
            ->assertSessionHasErrors('price');
        $this->assertSame('7.49', $price->fresh()->calculated_price);
        $this->post($edit.'/approve')->assertSessionHasErrors('price');   // already approved
    }

    public function test_a_price_change_is_a_new_row_and_the_old_one_is_ended_or_deactivated(): void
    {
        $old = PlanPriceFactory::new()->manual('9.99')->create(['plan_id' => $this->plan->id, 'effective_from' => now()->subDays(2)]);
        $new = PlanPriceFactory::new()->manual('8.99')->create(['plan_id' => $this->plan->id, 'effective_from' => now()->subDay()]);

        $this->assertSame($new->id, $this->plan->currentPrice('USD')->id);   // latest start wins

        $this->post("/admin/plans/{$this->plan->id}/prices/{$old->id}/end")->assertSessionHasNoErrors();
        $this->assertNotNull($old->fresh()->effective_to);
        $this->assertSame('9.99', $old->fresh()->calculated_price);   // history kept, amount untouched

        $this->post("/admin/plans/{$this->plan->id}/prices/{$new->id}/deactivate")->assertSessionHasNoErrors();
        $this->assertFalse($new->fresh()->is_active);
        $this->assertNull($this->plan->currentPrice('USD'));
        $this->assertSame(2, PlanPrice::count());
    }

    public function test_price_routes_are_scoped_to_their_plan(): void
    {
        $other = $this->draftPlan();
        $price = PlanPriceFactory::new()->pending()->create(['plan_id' => $other->id]);

        $this->post("/admin/plans/{$this->plan->id}/prices/{$price->id}/approve")->assertNotFound();
        $this->assertSame('pending', $price->fresh()->approval_status);
    }

    public function test_plan_page_shows_history_cost_and_below_cost_warning_to_admins(): void
    {
        PlanPriceFactory::new()->manual('9.99')->create(['plan_id' => $this->plan->id]);
        $this->plan->providerBundle->update(['provider_cost' => '12.0000']);

        $this->get("/admin/plans/{$this->plan->id}")->assertOk()
            ->assertSee('Price history')
            ->assertSee('$9.99')
            ->assertSee('12.0000 USD')
            ->assertSee('Below cost');
    }
}
