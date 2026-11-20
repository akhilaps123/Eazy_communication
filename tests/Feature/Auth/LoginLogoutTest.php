<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Tests\Feature\Database\DatabaseTestCase;

class LoginLogoutTest extends DatabaseTestCase
{
    private function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'customer@example.test', 'password' => 'secret-pass-123']);
    }

    public function test_login_page_renders_with_csrf_token(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="loginForm"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_valid_login_succeeds_and_records_last_login(): void
    {
        $user = $this->customer();
        $this->assertNull($user->last_login_at);

        $this->post('/login', ['email' => 'Customer@Example.test', 'password' => 'secret-pass-123'])
            ->assertRedirect(route('my-account'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $this->customer();

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'secret-pass-123']);

        $this->assertNotSame($before, session()->getId());
        $this->assertAuthenticated();
    }

    public function test_wrong_password_and_unknown_email_get_the_same_friendly_error(): void
    {
        $this->customer();
        $message = 'The email or password you entered is incorrect.';

        $this->from('/login')->post('/login', ['email' => 'customer@example.test', 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => $message]);
        $this->from('/login')->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => $message]);

        $this->assertGuest();
    }

    public function test_login_validation(): void
    {
        $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors(['email', 'password']);
        $this->post('/login', ['email' => 'not-an-email', 'password' => 'x'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_closed_soft_deleted_account_cannot_sign_in(): void
    {
        $this->customer()->delete();

        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'secret-pass-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $this->customer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['email' => 'customer@example.test', 'password' => 'wrong-password']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'secret-pass-123'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_remember_me_sets_a_remember_cookie(): void
    {
        $this->customer();

        $response = $this->post('/login', ['email' => 'customer@example.test', 'password' => 'secret-pass-123', 'remember' => 'on']);

        $names = array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies());
        $this->assertNotEmpty(array_filter($names, fn ($name) => str_starts_with($name, 'remember_web_')));
    }

    public function test_customer_is_returned_to_the_page_they_wanted_after_login(): void
    {
        $this->customer();

        $this->get('/cart')->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'secret-pass-123'])
            ->assertRedirect(route('cart'));
    }

    public function test_logout_signs_out_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->get('/my-account')->assertOk();

        $sessionBefore = session()->getId();
        $tokenBefore = session()->token();

        $this->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotSame($sessionBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
        $this->get('/my-account')->assertRedirect(route('login'));
    }

    public function test_logout_requires_post(): void
    {
        $this->actingAs($this->customer())->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();
    }

    public function test_my_account_page_has_csrf_protected_logout_form(): void
    {
        $this->actingAs($this->customer())->get('/my-account')
            ->assertOk()
            ->assertSee('id="logoutForm"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('data-logout', false);
    }
}
