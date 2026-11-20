<?php

namespace Tests\Feature\Cart;

use App\Models\CartItem;
use App\Models\Plan;
use App\Models\User;
use Database\Factories\PlanPriceFactory;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class CartTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private User $customer;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLiveCatalogue();
        $this->customer = User::factory()->create();
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan']);
        $this->plan = $this->sellablePlan(['slug' => 'jp-5gb', 'data_amount_mb' => 5000, 'validity_days' => 7], $japan, '9.99', ['compare_at_price' => '12.99']);
    }

    private function add(array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->customer)->postJson('/cart/items', $data + ['plan' => 'jp-5gb']);
    }

    private function item(): CartItem
    {
        return CartItem::query()->firstOrFail();
    }

    // ------------------------------------------------------------------ add

    public function test_guest_is_sent_to_login(): void
    {
        $this->post('/cart/items', ['plan' => 'jp-5gb'])->assertRedirect(route('login'));
        $this->get('/cart')->assertRedirect(route('login'));
        $this->postJson('/cart/items', ['plan' => 'jp-5gb'])->assertUnauthorized();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_customer_can_add_a_sellable_plan(): void
    {
        $this->add()->assertCreated()
            ->assertJson(['success' => true, 'message' => 'Added Japan eSIM (5 GB - 7 Days) to your cart.', 'cart_url' => route('cart'), 'item_count' => 1]);

        $this->assertSame([1, null], [$this->item()->quantity, $this->item()->trip_start_date]);
        $this->assertSame($this->customer->id, $this->item()->cart->user_id);
        $this->get('/cart')->assertSee('Added Japan eSIM (5 GB - 7 Days) to your cart.');   // confirmation on the cart page
    }

    public function test_browser_form_add_redirects_to_the_cart(): void
    {
        $this->actingAs($this->customer)->post('/cart/items', ['plan' => 'jp-5gb', 'quantity' => '2', 'trip_start_date' => now()->addDays(5)->format('d-m-Y')])
            ->assertRedirect(route('cart'))->assertSessionHas('status');

        $this->assertSame([2, now()->addDays(5)->toDateString()], [$this->item()->quantity, $this->item()->trip_start_date->toDateString()]);
    }

    public function test_same_plan_and_date_increases_quantity_and_a_different_date_is_a_new_line(): void
    {
        $date = now()->addWeek()->toDateString();
        $this->add(['trip_start_date' => $date])->assertCreated();
        $this->add(['trip_start_date' => $date, 'quantity' => 2])->assertCreated();
        $this->assertSame(1, CartItem::count());
        $this->assertSame(3, $this->item()->quantity);

        $this->add(['trip_start_date' => now()->addWeeks(2)->toDateString()])->assertCreated();
        $this->add()->assertCreated();   // no date
        $this->add()->assertCreated();   // no date again -> same line
        $this->assertSame(3, CartItem::count());
        $this->assertSame(2, CartItem::whereNull('trip_start_date')->value('quantity'));
    }

    public function test_quantity_is_limited_to_whole_numbers_from_1_to_10(): void
    {
        foreach ([0, -1, 1.5, '2.5', 11, 'abc'] as $bad) {
            $this->add(['quantity' => $bad])->assertStatus(422)->assertJsonPath('success', false);
        }
        $this->assertDatabaseCount('cart_items', 0);

        $this->add(['quantity' => 10])->assertCreated();
        $this->add(['quantity' => 1])->assertStatus(422)
            ->assertJsonPath('message', 'You can add up to 10 of the same plan for the same trip date (you already have 10 in your cart).');
        $this->assertSame(10, $this->item()->quantity);
    }

    public function test_trip_date_is_validated(): void
    {
        $this->add(['trip_start_date' => now()->subDay()->toDateString()])->assertStatus(422)->assertJsonPath('message', 'The trip start date cannot be in the past.');
        $this->add(['trip_start_date' => now()->addYears(2)->toDateString()])->assertStatus(422);
        $this->add(['trip_start_date' => 'next week'])->assertStatus(422);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_only_sellable_plans_can_be_added(): void
    {
        $country = $this->country();
        $this->sellablePlan(['slug' => 'inactive', 'is_active' => false], $country);
        $this->sellablePlan(['slug' => 'no-bundle'], $country, bundle: ['is_available' => false]);
        $this->sellablePlan(['slug' => 'pending'], $country, priceState: ['approval_status' => 'pending', 'is_active' => false]);
        $this->sellablePlan(['slug' => 'rejected'], $country, priceState: ['approval_status' => 'rejected']);
        $this->sellablePlan(['slug' => 'expired'], $country, priceState: ['effective_from' => now()->subDays(5), 'effective_to' => now()->subDay()]);
        $this->sellablePlan(['slug' => 'country-off'], $this->country(['is_active' => false]));

        foreach (['inactive', 'no-bundle', 'pending', 'rejected', 'expired', 'country-off', 'does-not-exist'] as $slug) {
            $this->add(['plan' => $slug])->assertStatus(422)->assertJsonPath('message', 'This plan is not available right now.');
        }
        $this->assertDatabaseCount('cart_items', 0);
    }

    // ------------------------------------------------------------- manage

    public function test_cart_page_shows_server_calculated_lines_and_totals(): void
    {
        $this->add(['quantity' => 3, 'trip_start_date' => now()->addDays(3)->toDateString()]);

        $this->get('/cart')->assertOk()
            ->assertSee('data-cart-live', false)
            ->assertSee('Japan eSIM')->assertSee('5 GB - 7 Days')
            ->assertSee('Trip Start Date: '.now()->addDays(3)->toDateString())
            ->assertSee('$9.99')->assertSee('$29.97')       // line total
            ->assertSee('$38.97')                            // crossed-out line total (12.99 x 3)
            ->assertSee('<span class="cart-summary-amount" id="cartSummaryAmount">$29.97</span>', false)
            ->assertDontSee('$34.99')                        // static sample price
            ->assertDontSee('cartItemJapan', false);
    }

    public function test_customer_can_update_and_remove_items_and_the_cart_persists(): void
    {
        $this->add();
        $id = $this->item()->id;

        $this->patch("/cart/items/{$id}", ['quantity' => 4])->assertRedirect(route('cart'));
        $this->assertSame(4, $this->item()->quantity);
        $this->get('/cart')->assertSee('$39.96');

        foreach ([0, 11, 2.5, ''] as $bad) {
            $this->patch("/cart/items/{$id}", ['quantity' => $bad])->assertSessionHasErrors('quantity');
        }
        $this->assertSame(4, $this->item()->quantity);

        // Persists across requests / sign-ins.
        $this->post('/logout');
        $this->actingAs($this->customer->fresh())->get('/cart')->assertSee('$39.96');

        $this->delete("/cart/items/{$id}")->assertRedirect(route('cart'));
        $this->assertDatabaseCount('cart_items', 0);
        $this->get('/cart')->assertSee('class="cart-empty-state active"', false)->assertSee('Explore eSIM Plans');
    }

    public function test_customers_cannot_touch_another_customers_cart(): void
    {
        $this->add(['quantity' => 2]);
        $id = $this->item()->id;
        $other = User::factory()->create();

        $this->actingAs($other)->patch("/cart/items/{$id}", ['quantity' => 9])->assertNotFound();
        $this->actingAs($other)->delete("/cart/items/{$id}")->assertNotFound();
        $this->actingAs($other)->get('/cart')->assertDontSee('Japan eSIM')->assertSee('class="cart-empty-state active"', false);

        $this->assertSame(2, $this->item()->quantity);
    }

    public function test_gstin_is_saved_validated_and_removable(): void
    {
        $this->add();

        $this->post('/cart/gstin', ['gstin' => '22aaaaa0000a1z5'])->assertRedirect(route('cart'));
        $this->assertSame('22AAAAA0000A1Z5', $this->item()->cart->billing_gstin);
        $this->get('/cart')->assertSee('22AAAAA0000A1Z5');

        $this->post('/cart/gstin', ['gstin' => 'NOT-A-GSTIN'])->assertSessionHasErrors('gstin');
        $this->assertSame('22AAAAA0000A1Z5', $this->item()->cart->fresh()->billing_gstin);

        $this->post('/cart/gstin', ['gstin' => ''])->assertRedirect(route('cart'));
        $this->assertNull($this->item()->cart->fresh()->billing_gstin);
    }

    public function test_items_that_become_unavailable_are_flagged_excluded_and_block_checkout(): void
    {
        $other = $this->sellablePlan(['slug' => 'other'], price: '5.50');
        $this->add();
        $this->add(['plan' => 'other']);

        $other->update(['is_active' => false]);   // the admin deactivates it after it was added

        $this->get('/cart')->assertOk()
            ->assertSee('No longer available – remove this item to continue.')
            ->assertSee('<span class="cart-summary-amount" id="cartSummaryAmount">$9.99</span>', false)   // excluded
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('href="'.route('checkout').'" class="btn-checkout-cta"', false);
        $this->assertSame(2, CartItem::count());   // never silently deleted

        $this->get('/checkout')->assertRedirect(route('cart'))->assertSessionHasErrors('cart');
    }

    public function test_cart_writes_are_404_while_the_live_catalogue_is_off(): void
    {
        config(['catalogue.live' => false]);

        $this->add()->assertNotFound();
        $this->actingAs($this->customer)->post('/cart/gstin', ['gstin' => ''])->assertNotFound();
        $this->get('/cart')->assertOk()->assertSee('cartItemJapan', false)->assertSee('$34.99')->assertDontSee('data-cart-live', false);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_routes_need_csrf_login_and_are_rate_limited(): void
    {
        foreach (['cart.items.store', 'cart.items.update', 'cart.items.destroy', 'cart.gstin'] as $name) {
            $middleware = \Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('web', $middleware);    // session + CSRF
            $this->assertContains('auth', $middleware);
            $this->assertContains('throttle:cart', $middleware);
        }
        $this->assertContains('throttle:checkout', \Route::getRoutes()->getByName('checkout.submit')->gatherMiddleware());
    }
}
