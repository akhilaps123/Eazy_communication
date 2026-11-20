<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Database\DatabaseTestCase;

/**
 * JSON responses for clients sending "Accept: application/json" (e.g. Postman).
 * Browser behaviour is covered by the other tests in this folder, which are unchanged.
 *
 * Note: Laravel skips CSRF checks while running tests, so the CSRF/cookie flow
 * is additionally verified against a temporary server (see the report).
 */
class JsonAuthResponsesTest extends DatabaseTestCase
{
    private const EMAIL = 'customer@example.test';

    private const PASSWORD = 'secret-pass-123';

    private function customer(): User
    {
        return User::factory()->create(['name' => 'Test Customer', 'email' => self::EMAIL, 'password' => self::PASSWORD]);
    }

    private function registration(array $overrides = []): array
    {
        return $overrides + [
            'fullName' => 'Test Customer',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms' => true,
        ];
    }

    // 1. Browser login is unchanged
    public function test_browser_login_still_redirects(): void
    {
        $this->customer();

        $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertRedirect(route('my-account'));
        $this->assertAuthenticated();
    }

    public function test_browser_errors_still_redirect_back_with_messages(): void
    {
        $this->customer();

        $this->from('/login')->post('/login', ['email' => self::EMAIL, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'The email or password you entered is incorrect.']);

        $this->from('/register')->post('/register', $this->registration())
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');
    }

    // 2. JSON login success
    public function test_json_login_returns_success_and_safe_user_fields_only(): void
    {
        $user = $this->customer();

        $response = $this->postJson('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Login successful.',
                'user' => ['id' => $user->id, 'name' => 'Test Customer', 'email' => self::EMAIL],
            ]);

        $this->assertAuthenticatedAs($user);
        foreach (['password', 'remember_token', 'role', 'deleted_at'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertStringNotContainsString($user->password, $response->getContent());
    }

    // 3. JSON invalid login
    public function test_json_invalid_login_returns_422(): void
    {
        $this->customer();
        $message = 'The email or password you entered is incorrect.';

        foreach ([self::EMAIL, 'nobody@example.test'] as $email) {
            $this->postJson('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertStatus(422)
                ->assertJson(['success' => false, 'message' => $message, 'errors' => ['email' => [$message]]]);
        }
        $this->assertGuest();
    }

    public function test_json_login_validation_errors(): void
    {
        $this->postJson('/login', [])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Please enter your email address.'])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_json_login_lockout_returns_429(): void
    {
        $this->customer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->postJson('/login', ['email' => self::EMAIL, 'password' => 'wrong-password'])->assertStatus(422);
        }

        $response = $this->postJson('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertJson(['success' => false]);
        $this->assertStringContainsString('Too many sign-in attempts', $response->json('message'));
        $this->assertGuest();
    }

    // 4. JSON registration
    public function test_json_registration_returns_201_and_signs_in(): void
    {
        $this->postJson('/register', $this->registration())
            ->assertCreated()
            ->assertExactJson(['success' => true, 'message' => 'Customer registered successfully.']);

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_json_registration_ignores_role_input(): void
    {
        $this->postJson('/register', $this->registration(['role' => 'admin', 'is_admin' => true]))->assertCreated();

        $this->assertSame(User::ROLE_CUSTOMER, User::where('email', self::EMAIL)->value('role'));
    }

    // 5. Duplicate email
    public function test_json_duplicate_email_returns_validation_error(): void
    {
        $this->customer();
        $message = 'An account with this email already exists. Please sign in instead.';

        $this->postJson('/register', $this->registration(['email' => 'Customer@Example.TEST']))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => $message, 'errors' => ['email' => [$message]]]);

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
        $this->assertGuest();
    }

    public function test_json_registration_validation_errors(): void
    {
        $this->postJson('/register', $this->registration(['password_confirmation' => 'different-1', 'terms' => false]))
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['password', 'terms']);
        $this->assertDatabaseCount('users', 0);
    }

    // 6 + 7. Logout, then protected pages require authentication
    public function test_json_logout_returns_success_and_protected_pages_need_login_again(): void
    {
        $this->postJson('/login', ['email' => $this->customer()->email, 'password' => self::PASSWORD])->assertOk();
        $tokenBefore = session()->token();

        $this->postJson('/logout')
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Logout successful.']);

        $this->assertGuest();
        $this->assertNotSame($tokenBefore, session()->token());   // CSRF token regenerated

        $this->getJson('/my-account')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Please sign in to continue.']);
        $this->postJson('/logout')->assertUnauthorized();

        // Browsers are still redirected to the login page.
        $this->get('/my-account')->assertRedirect(route('login'));
    }

    // 8. Session cookies
    public function test_json_login_sets_session_and_xsrf_cookies_and_session_keeps_customer_signed_in(): void
    {
        $this->customer();

        $this->postJson('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertCookie(config('session.cookie'))
            ->assertCookie('XSRF-TOKEN');

        // Next request in the same session is signed in.
        $this->get('/my-account')->assertOk();
        $this->get('/cart')->assertOk();
    }

    public function test_json_remember_me_sets_remember_cookie(): void
    {
        $this->customer();

        $response = $this->postJson('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD, 'remember' => true]);

        $names = array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies());
        $this->assertNotEmpty(array_filter($names, fn ($name) => str_starts_with($name, 'remember_web_')));
    }

    public function test_json_request_from_signed_in_customer_to_guest_routes_returns_409(): void
    {
        $this->actingAs($this->customer());

        foreach (['/login', '/register', '/forgot-password'] as $uri) {
            $this->postJson($uri, [])->assertStatus(409)->assertJson(['success' => false]);
        }

        // Browsers are still redirected to My Account.
        $this->post('/login', [])->assertRedirect(route('my-account'));
    }

    // Forgot / reset password
    public function test_json_forgot_password_returns_same_generic_message(): void
    {
        Notification::fake();
        $this->customer();

        foreach ([self::EMAIL, 'nobody@example.test'] as $email) {
            $this->postJson('/forgot-password', ['identity' => $email])
                ->assertOk()
                ->assertExactJson(['success' => true, 'message' => ForgotPasswordController::STATUS_MESSAGE]);
        }

        $this->postJson('/forgot-password', ['identity' => 'not-an-email'])
            ->assertStatus(422)->assertJson(['success' => false])->assertJsonValidationErrors('identity');
    }

    public function test_json_password_reset_success_and_invalid_token(): void
    {
        Notification::fake();
        $user = $this->customer();
        $token = Password::createToken($user);
        $body = ['token' => $token, 'email' => self::EMAIL, 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'];

        $this->postJson('/reset-password', $body)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => ResetPasswordController::SUCCESS_MESSAGE]);
        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));

        // Same token again: rejected.
        $this->postJson('/reset-password', $body)
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => ResetPasswordController::INVALID_MESSAGE]);
    }

    public function test_json_rate_limit_returns_429_json(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/forgot-password', ['identity' => 'nobody@example.test'])->assertOk();
        }

        $this->postJson('/forgot-password', ['identity' => 'nobody@example.test'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson(['success' => false]);
    }
}
