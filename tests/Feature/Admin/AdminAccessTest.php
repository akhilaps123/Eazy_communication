<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\PlanPrice;
use App\Models\User;
use Database\Factories\PlanPriceFactory;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Catalogue\CatalogueFixtures;
use Tests\Feature\Database\DatabaseTestCase;

class AdminAccessTest extends DatabaseTestCase
{
    use CatalogueFixtures;

    private const ADMIN_PAGES = ['/admin', '/admin/countries', '/admin/countries/create', '/admin/plans', '/admin/plans/create'];

    public function test_guests_are_sent_to_login(): void
    {
        foreach (self::ADMIN_PAGES as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }
        $this->post('/admin/countries', ['name' => 'X', 'iso2' => 'XX'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('countries', 0);
    }

    public function test_customers_get_403_and_cannot_change_anything(): void
    {
        $this->actingAs(User::factory()->create());
        $country = $this->country();
        $plan = $this->draftPlan();
        $price = PlanPriceFactory::new()->pending()->create(['plan_id' => $plan->id]);
        $countriesBefore = Country::count();

        foreach ([...self::ADMIN_PAGES, "/admin/countries/{$country->id}", "/admin/plans/{$plan->id}"] as $page) {
            $this->get($page)->assertForbidden();
        }
        $this->post('/admin/countries', ['name' => 'Hackland', 'iso2' => 'HK'])->assertForbidden();
        $this->post("/admin/countries/{$country->id}/toggle")->assertForbidden();
        $this->delete("/admin/countries/{$country->id}")->assertForbidden();
        $this->post("/admin/plans/{$plan->id}/activate")->assertForbidden();
        $this->post("/admin/plans/{$plan->id}/prices", ['pricing_method' => 'manual', 'manual_price' => '0.01'])->assertForbidden();
        $this->post("/admin/plans/{$plan->id}/prices/{$price->id}/approve")->assertForbidden();

        $this->assertSame($countriesBefore, Country::count());
        $this->assertTrue($country->fresh()->is_active);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(PlanPrice::APPROVAL_PENDING, $price->fresh()->approval_status);
        $this->assertSame(1, PlanPrice::count());
    }

    public function test_admins_can_open_every_admin_page(): void
    {
        $this->actingAs($this->admin());
        $country = $this->country();
        $plan = $this->draftPlan(country: $country);

        foreach ([...self::ADMIN_PAGES, "/admin/countries/{$country->id}", "/admin/countries/{$country->id}/edit", "/admin/plans/{$plan->id}", "/admin/plans/{$plan->id}/edit"] as $page) {
            $this->get($page)->assertOk()->assertSee('Eazy Communication', false)->assertSee('css/admin.css', false);
        }
    }

    public function test_admin_pages_use_the_separate_admin_layout_not_the_website_layout(): void
    {
        $this->actingAs($this->admin())->get('/admin')
            ->assertSee('css/admin.css', false)
            ->assertDontSee('css/header.css', false)
            ->assertSee('noindex, nofollow', false);
    }

    // ---------------------------------------------------------- eazy:make-admin

    public function test_make_admin_creates_an_admin_with_a_hashed_password_and_never_prints_it(): void
    {
        $this->artisan('eazy:make-admin')
            ->expectsQuestion('Admin full name', 'Site Admin')
            ->expectsQuestion('Admin email address', 'Admin@Example.test')
            ->expectsQuestion('Password (hidden)', 'super-secret-pass-1')
            ->expectsQuestion('Confirm password (hidden)', 'super-secret-pass-1')
            ->expectsOutputToContain('Admin account created for admin@example.test')
            ->doesntExpectOutputToContain('super-secret-pass-1')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertNotSame('super-secret-pass-1', $admin->getRawOriginal('password'));
        $this->assertTrue(Hash::check('super-secret-pass-1', $admin->password));
    }

    public function test_make_admin_refuses_an_email_that_already_has_an_account(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->artisan('eazy:make-admin')
            ->expectsQuestion('Admin full name', 'Someone')
            ->expectsQuestion('Admin email address', 'taken@example.test')
            ->expectsOutputToContain('already exists')
            ->assertFailed();

        $this->assertSame(0, User::where('role', User::ROLE_ADMIN)->count());
    }

    public function test_make_admin_asks_before_creating_another_admin(): void
    {
        $this->admin();

        $this->artisan('eazy:make-admin')
            ->expectsConfirmation('1 admin account(s) already exist. Create another admin account?', 'no')
            ->expectsOutputToContain('No account was created')
            ->assertSuccessful();

        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->count());
    }

    public function test_make_admin_rejects_mismatched_or_short_passwords(): void
    {
        $this->artisan('eazy:make-admin')
            ->expectsQuestion('Admin full name', 'Site Admin')
            ->expectsQuestion('Admin email address', 'admin@example.test')
            ->expectsQuestion('Password (hidden)', 'super-secret-pass-1')
            ->expectsQuestion('Confirm password (hidden)', 'different-pass-1')
            ->assertFailed();

        $this->artisan('eazy:make-admin')
            ->expectsQuestion('Admin full name', 'Site Admin')
            ->expectsQuestion('Admin email address', 'admin@example.test')
            ->expectsQuestion('Password (hidden)', 'short')
            ->expectsQuestion('Confirm password (hidden)', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
