<?php

namespace Tests\Feature\Cart;

use App\Models\Order;
use App\Models\User;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class OrderPageTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private User $owner;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLiveCatalogue();
        $this->owner = User::factory()->create();
        $korea = $this->country(['name' => 'South Korea', 'iso2' => 'KR', 'slug' => 'south-korea']);
        $this->sellablePlan(['slug' => 'kr-3gb', 'data_amount_mb' => 3000, 'validity_days' => 5], $korea, '7.25',
            bundle: ['bundle_code' => 'SECRET_KR_CODE', 'provider_cost' => '3.3333', 'provider_currency' => 'USD']);

        $this->actingAs($this->owner);
        $this->postJson('/cart/items', ['plan' => 'kr-3gb', 'quantity' => 2, 'trip_start_date' => now()->addDays(4)->toDateString()])->assertCreated();
        $this->get('/checkout');
        $this->post('/checkout', ['country' => 'KR', 'postalCode' => '04524', 'expected_total' => '14.50', 'checkout_token' => session('checkout.token')]);
        $this->order = Order::firstOrFail();
    }

    public function test_owner_sees_the_order_received_page(): void
    {
        $this->get(route('orders.show', $this->order->order_number))->assertOk()
            ->assertSee('Order Received')
            ->assertSee($this->order->order_number)
            ->assertSee('Awaiting Payment')
            ->assertSee('South Korea eSIM · 3 GB - 5 Days')
            ->assertSee('Countries: South Korea')
            ->assertSee('Trip start '.now()->addDays(4)->toDateString())
            ->assertSee('2 × $7.25')
            ->assertSee('$14.50')
            ->assertSee('Payment will be available soon.')
            ->assertSee('href="'.route('home').'"', false);
    }

    public function test_internal_and_false_information_never_appears(): void
    {
        $html = $this->get(route('orders.show', $this->order->order_number))->getContent();

        foreach (['SECRET_KR_CODE', '3.3333', '3.33', 'margin', 'provider', 'activation details have been sent', 'QR code'] as $hidden) {
            $this->assertStringNotContainsStringIgnoringCase($hidden, $html, "Order page leaked [{$hidden}]");
        }
    }

    public function test_other_customers_and_guests_cannot_view_the_order(): void
    {
        $this->actingAs(User::factory()->create())->get(route('orders.show', $this->order->order_number))->assertNotFound();

        $this->post('/logout');
        $this->get(route('orders.show', $this->order->order_number))->assertRedirect(route('login'));
    }

    public function test_unknown_order_numbers_return_404(): void
    {
        $this->get('/orders/EC-000000-ZZZZZZ')->assertNotFound();
    }

    public function test_public_api_still_never_exposes_internal_data(): void
    {
        $json = $this->getJson('/api/plans/kr-3gb')->assertOk()->getContent();

        $this->assertStringNotContainsString('SECRET_KR_CODE', $json);
        $this->assertStringNotContainsString('3.3333', $json);
    }
}
