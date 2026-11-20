<?php

namespace Tests\Feature\Cart;

use App\Exceptions\CartException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\User;
use App\Services\CheckoutService;
use Database\Factories\PlanPriceFactory;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class CheckoutTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private User $customer;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLiveCatalogue();
        $this->customer = User::factory()->create(['name' => 'Asha Customer', 'email' => 'asha@example.test']);
        $japan = $this->country(['name' => 'Japan', 'iso2' => 'JP', 'slug' => 'japan']);
        $this->plan = $this->sellablePlan(['slug' => 'jp-5gb', 'name' => 'Japan 5GB', 'data_amount_mb' => 5000, 'validity_days' => 7], $japan, '9.99',
            bundle: ['bundle_code' => 'SECRET_JP_5GB', 'provider_cost' => '4.2500', 'provider_currency' => 'USD']);
        $this->actingAs($this->customer);
    }

    private function addToCart(string $slug = 'jp-5gb', int $quantity = 1, ?string $date = null): void
    {
        $this->postJson('/cart/items', ['plan' => $slug, 'quantity' => $quantity, 'trip_start_date' => $date])->assertCreated();
    }

    /** Opens the checkout page (creates the one-time token) and returns the token. */
    private function openCheckout(): string
    {
        $this->get('/checkout')->assertOk();

        return session('checkout.token');
    }

    private function submit(string $token, array $overrides = [])
    {
        return $this->post('/checkout', $overrides + [
            'country' => 'IN', 'postalCode' => '560001', 'gstin' => '',
            'expected_total' => '9.99', 'checkout_token' => $token,
        ]);
    }

    // ------------------------------------------------------------ the page

    public function test_checkout_page_shows_the_real_customer_and_summary_without_demo_content(): void
    {
        $this->addToCart(quantity: 2);

        $this->get('/checkout')->assertOk()
            ->assertSee('Asha Customer')->assertSee('asha@example.test')
            ->assertDontSee('johndoe1997@gmail.com')->assertDontSee('John Doe')
            ->assertSee('Japan eSIM · 5 GB - 7 Days × 2')->assertSee('$19.98')
            ->assertSee('name="expected_total" value="19.98"', false)
            ->assertSee('data-value="IN">India</li>', false)
            ->assertDontSee('promoCodeInput', false)->assertDontSee('EAZY10')
            ->assertSee('Coming soon')->assertSee('Place order')
            ->assertDontSee('saveFuturePurchases', false)
            ->assertDontSee('SECRET_JP_5GB')->assertDontSee('4.25');
    }

    public function test_empty_cart_cannot_check_out(): void
    {
        $this->get('/checkout')->assertRedirect(route('cart'))->assertSessionHasErrors('cart');

        $this->withSession(['checkout.token' => $token = Str::random(40)]);
        $this->submit($token)->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => 'Your cart is empty.']);
        $this->assertDatabaseCount('orders', 0);
    }

    // -------------------------------------------------------- order creation

    public function test_checkout_creates_a_pending_payment_order_with_immutable_snapshots(): void
    {
        $tripDate = now()->addDays(10)->toDateString();
        $this->addToCart(quantity: 3, date: $tripDate);
        $this->post('/cart/gstin', ['gstin' => '29ABCDE1234F1Z5']);
        $token = $this->openCheckout();

        $response = $this->submit($token, ['expected_total' => '29.97', 'gstin' => '29ABCDE1234F1Z5']);

        $order = Order::with('items')->firstOrFail();
        $response->assertRedirect(route('orders.show', $order->order_number));
        $this->assertMatchesRegularExpression('/^EC-'.now()->format('ymd').'-[A-Z0-9]{6}$/', $order->order_number);
        $this->assertSame(
            [$this->customer->id, 'USD', '29.97', '0.00', '0.00', '29.97', null, 'pending_payment', 'not_started', null, 'Asha Customer', 'asha@example.test', 'IN', '560001', '29ABCDE1234F1Z5', null],
            [$order->user_id, $order->currency, $order->subtotal, $order->discount_total, $order->tax_total, $order->total, $order->tax_rate,
                $order->payment_status, $order->fulfillment_status, $order->expires_at, $order->billing_name, $order->billing_email,
                $order->billing_country, $order->billing_postal_code, $order->billing_gstin, $order->promo_code],
        );

        $item = $order->items->sole();
        $this->assertSame(
            [$this->plan->id, 'Japan eSIM · 5 GB - 7 Days', 'SECRET_JP_5GB', '9.99', 3, '29.97', '4.2500', 'USD', $tripDate],
            [$item->plan_id, $item->plan_name, $item->bundle_code, $item->unit_price, $item->quantity, $item->line_total,
                $item->unit_provider_cost, $item->provider_currency, $item->trip_start_date->toDateString()],
        );

        // Cart emptied; nothing paid, nothing ordered from eSIM Go, no eSIM.
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('provider_orders', 0);
        $this->assertDatabaseCount('esims', 0);

        // Later admin edits never change the order.
        $this->plan->update(['name' => 'Renamed', 'validity_days' => 30]);
        PlanPriceFactory::new()->manual('19.99')->create(['plan_id' => $this->plan->id, 'effective_from' => now()->subMinute()]);
        $this->assertSame(['Japan eSIM · 5 GB - 7 Days', '9.99', '29.97'], [$item->fresh()->plan_name, $item->fresh()->unit_price, $order->fresh()->total]);
        $this->expectException(LogicException::class);
        $item->fresh()->update(['unit_price' => '0.01']);
    }

    public function test_order_numbers_are_unique(): void
    {
        $this->addToCart();
        $this->submit($this->openCheckout());
        $this->addToCart();
        $this->submit($this->openCheckout());

        $numbers = Order::pluck('order_number');
        $this->assertCount(2, $numbers->unique());
        foreach ($numbers as $number) {
            $this->assertTrue(\App\Support\OrderNumber::isValid($number));
            $this->assertLessThanOrEqual(30, strlen($number));
        }
    }

    // ------------------------------------------------------------- pricing

    public function test_client_prices_are_ignored_and_totals_come_from_the_server(): void
    {
        $this->postJson('/cart/items', ['plan' => 'jp-5gb', 'price' => '0.01', 'unit_price' => '0.01', 'total' => '0.01'])->assertCreated();
        $token = $this->openCheckout();

        $this->submit($token, ['unit_price' => '0.01', 'subtotal' => '0.01', 'total' => '0.01', 'discount_total' => '9.99'])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(['9.99', '9.99', '0.00'], [$order->subtotal, $order->total, $order->discount_total]);
    }

    public function test_a_tampered_expected_total_creates_no_order(): void
    {
        $this->addToCart();
        $token = $this->openCheckout();

        $this->submit($token, ['expected_total' => '0.01'])->assertRedirect(route('cart'))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);   // cart kept
    }

    public function test_price_change_after_opening_checkout_is_detected(): void
    {
        $this->addToCart();
        $token = $this->openCheckout();   // customer sees $9.99

        PlanPriceFactory::new()->manual('11.49')->create(['plan_id' => $this->plan->id, 'effective_from' => now()->subMinute()]);

        $this->submit($token)->assertRedirect(route('cart'))
            ->assertSessionHasErrors(['cart' => 'Prices in your cart have changed since you opened checkout. Please review your cart before placing the order.']);
        $this->assertDatabaseCount('orders', 0);
        $this->get('/cart')->assertSee('$11.49');   // cart shows the new current price
    }

    public function test_decimal_totals_have_no_floating_point_errors(): void
    {
        $country = $this->country();
        $this->sellablePlan(['slug' => 'ten-cents'], $country, '0.10', bundle: ['provider_cost' => '0.0500']);
        $this->sellablePlan(['slug' => 'twenty-cents'], $country, '0.20', bundle: ['provider_cost' => '0.0500']);
        $this->addToCart('ten-cents', 3);
        $this->addToCart('twenty-cents', 1);

        $this->get('/cart')->assertSee('<span class="cart-summary-amount" id="cartSummaryAmount">$0.50</span>', false);   // 0.1*3 + 0.2 in floats = 0.5000000000000001
        $this->submit($this->openCheckout(), ['expected_total' => '0.50'])->assertRedirect();
        $this->assertSame('0.50', Order::firstOrFail()->total);
    }

    public function test_unavailable_item_blocks_order_creation(): void
    {
        $this->addToCart();
        $token = $this->openCheckout();
        $this->plan->providerBundle->update(['is_available' => false]);

        $this->submit($token)->assertRedirect(route('cart'))
            ->assertSessionHasErrors(['cart' => 'Some items in your cart are no longer available. Please remove them to continue.']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    // ---------------------------------------------------------- validation

    public function test_billing_fields_are_validated(): void
    {
        $this->addToCart();
        $token = $this->openCheckout();

        $this->submit($token, ['country' => 'India'])->assertSessionHasErrors('country');   // names are not ISO2 codes
        $this->submit($token, ['country' => 'XX'])->assertSessionHasErrors('country');
        $this->submit($token, ['country' => ''])->assertSessionHasErrors('country');
        $this->submit($token, ['postalCode' => ''])->assertSessionHasErrors('postalCode');
        $this->submit($token, ['postalCode' => str_repeat('9', 21)])->assertSessionHasErrors('postalCode');
        $this->submit($token, ['gstin' => 'BAD'])->assertSessionHasErrors('gstin');
        $this->submit($token, ['expected_total' => 'free'])->assertSessionHasErrors('expected_total');
        $this->assertDatabaseCount('orders', 0);

        $this->submit($token, ['country' => 'gb', 'postalCode' => 'SW1A 1AA'])->assertRedirect();
        $this->assertSame(['GB', 'SW1A 1AA'], [Order::firstOrFail()->billing_country, Order::firstOrFail()->billing_postal_code]);
    }

    // ---------------------------------------------------------- duplicates

    public function test_duplicate_submissions_and_refreshes_do_not_create_another_order(): void
    {
        $this->addToCart();
        $token = $this->openCheckout();

        $first = $this->submit($token);
        $order = Order::firstOrFail();
        $first->assertRedirect(route('orders.show', $order->order_number));

        // Same token again (double click / back + resubmit / refresh of the POST).
        $this->submit($token)->assertRedirect(route('orders.show', $order->order_number));
        $this->submit($token)->assertRedirect(route('orders.show', $order->order_number));

        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItem::count());
    }

    public function test_a_stale_or_forged_token_is_rejected(): void
    {
        $this->addToCart();
        $this->openCheckout();

        $this->submit(Str::random(40))->assertRedirect(route('checkout'))->assertSessionHasErrors('checkout_token');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_failed_transaction_leaves_no_partial_order_and_keeps_the_cart(): void
    {
        $this->addToCart(quantity: 2);
        OrderItem::creating(fn () => throw new RuntimeException('simulated failure'));

        try {
            app(CheckoutService::class)->placeOrder($this->customer, ['billing_country' => 'IN', 'billing_postal_code' => '560001', 'billing_gstin' => null], '19.98', Str::random(40));
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(2, CartItem::firstOrFail()->quantity);
    }

    public function test_service_rejects_empty_cart_with_a_customer_message(): void
    {
        $this->expectException(CartException::class);
        app(CheckoutService::class)->placeOrder($this->customer, ['billing_country' => 'IN', 'billing_postal_code' => '1', 'billing_gstin' => null], '0.00', Str::random(40));
    }

    // ---------------------------------------------------------- live switch

    public function test_with_the_live_catalogue_off_checkout_is_the_static_page_and_post_is_501(): void
    {
        config(['catalogue.live' => false]);

        $this->get('/checkout')->assertOk()->assertSee('johndoe1997@gmail.com')->assertSee('onsubmit="event.preventDefault();"', false)->assertDontSee('data-checkout-live', false);
        $this->post('/checkout', ['country' => 'IN'])->assertStatus(501);
        $this->assertDatabaseCount('orders', 0);
    }
}
