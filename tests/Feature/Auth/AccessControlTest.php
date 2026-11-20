<?php

namespace Tests\Feature\Auth;

use App\Models\Esim;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\ProviderBundle;
use App\Models\ProviderOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Database\DatabaseTestCase;

class AccessControlTest extends DatabaseTestCase
{
    public const PUBLIC_PAGES = [
        '/', '/about', '/blog', '/blog-detail', '/guides', '/new-esim', '/japan-esim',
        '/esim-support', '/check-usage', '/login', '/register', '/forgot-password',
    ];

    public const CUSTOMER_PAGES = ['/my-account', '/cart', '/checkout'];

    public function test_public_pages_remain_open_to_guests(): void
    {
        foreach (self::PUBLIC_PAGES as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_guests_are_redirected_to_login_from_customer_pages(): void
    {
        foreach (self::CUSTOMER_PAGES as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }
        $this->post('/checkout')->assertRedirect(route('login'));
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_signed_in_customers_can_open_their_account_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::CUSTOMER_PAGES as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_signed_in_customers_are_sent_from_guest_pages_to_my_account(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/login', '/register', '/forgot-password', '/reset-password/abc'] as $page) {
            $this->get($page)->assertRedirect(route('my-account'));
        }
    }

    public function test_header_follows_login_state(): void
    {
        $this->get('/about')
            ->assertSee('class="header-user-btn"', false)
            ->assertDontSee('class="header-avatar-pill', false);

        $this->actingAs(User::factory()->create())->get('/')
            ->assertSee('class="header-avatar-pill', false)
            ->assertDontSee('class="header-user-btn"', false);
    }

    public function test_customers_can_only_view_their_own_orders_and_esims(): void
    {
        [$alice, $aliceOrder, $aliceEsim] = $this->customerWithOrder('alice', 1);
        [$bob, $bobOrder, $bobEsim] = $this->customerWithOrder('bob', 2);

        $this->assertTrue(Gate::forUser($alice)->allows('view', $aliceOrder));
        $this->assertTrue(Gate::forUser($alice)->allows('view', $aliceEsim));

        $this->assertTrue(Gate::forUser($alice)->denies('view', $bobOrder));
        $this->assertTrue(Gate::forUser($alice)->denies('view', $bobEsim));
        $this->assertTrue(Gate::forUser($bob)->denies('view', $aliceOrder));
        $this->assertTrue(Gate::forUser($bob)->denies('view', $aliceEsim));
    }

    public function test_an_admin_role_alone_does_not_bypass_customer_ownership(): void
    {
        [, $order, $esim] = $this->customerWithOrder('alice', 1);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        // Admin access to customer records will be designed in Phase 9, not granted implicitly.
        $this->assertTrue(Gate::forUser($admin)->denies('view', $order));
        $this->assertTrue(Gate::forUser($admin)->denies('view', $esim));
    }

    public function test_admin_middleware_blocks_customers_and_allows_admins(): void
    {
        Route::middleware(['web', 'auth', 'admin'])->get('/__test-admin-only', fn () => 'admin area');

        $this->get('/__test-admin-only')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/__test-admin-only')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $this->actingAs($admin)->get('/__test-admin-only')->assertOk()->assertSee('admin area');
    }

    /** Phase 5 added the admin panel: every /admin route must require login AND the admin role. */
    public function test_every_admin_route_requires_login_and_the_admin_role(): void
    {
        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin'));

        $this->assertNotEmpty($adminRoutes);
        foreach ($adminRoutes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, $route->uri());
            $this->assertContains('admin', $middleware, $route->uri());
        }
    }

    /** Test-only records in the in-memory database. */
    private function customerWithOrder(string $name, int $n): array
    {
        $user = User::factory()->create(['email' => "{$name}@example.test"]);
        $bundle = ProviderBundle::create(['provider' => 'esimgo', 'bundle_code' => "TEST_{$n}", 'name' => 'Test']);
        $plan = Plan::create([
            'provider_bundle_id' => $bundle->id, 'slug' => "test-{$n}", 'name' => 'Test plan',
            'coverage_type' => 'local', 'data_type' => 'fixed', 'data_amount_mb' => 1024, 'validity_days' => 7,
        ]);
        $order = Order::create([
            'order_number' => "TEST-{$n}", 'user_id' => $user->id, 'currency' => 'XXX', 'subtotal' => '1.00',
            'total' => '1.00', 'billing_name' => $user->name, 'billing_email' => $user->email,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'plan_id' => $plan->id, 'plan_name' => 'Test plan', 'bundle_code' => "TEST_{$n}",
            'unit_price' => '1.00', 'quantity' => 1, 'line_total' => '1.00',
        ]);
        $providerOrder = ProviderOrder::create(['order_id' => $order->id, 'provider' => 'esimgo', 'our_reference' => "REF-{$n}"]);
        $esim = Esim::create([
            'user_id' => $user->id, 'order_item_id' => $item->id, 'provider_order_id' => $providerOrder->id, 'iccid' => "000{$n}",
        ]);

        return [$user, $order, $esim];
    }
}
