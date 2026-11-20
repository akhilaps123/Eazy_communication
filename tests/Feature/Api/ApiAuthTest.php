<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\AuthController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\Database\DatabaseTestCase;

/**
 * Customer API with Sanctum Bearer tokens (routes/api.php).
 * Runs on the in-memory test database only (DatabaseTestCase).
 */
class ApiAuthTest extends DatabaseTestCase
{
    private const EMAIL = 'customer@example.test';

    private const PASSWORD = 'secret-pass-123';

    private function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['name' => 'Test Customer', 'email' => self::EMAIL, 'password' => self::PASSWORD]);
    }

    private function login(array $body = []): TestResponse
    {
        return $this->postJson('/api/login', $body + ['email' => self::EMAIL, 'password' => self::PASSWORD]);
    }

    /**
     * Sends a request with a Bearer token, like Postman. Guards are reset first
     * because the test app is reused between requests (a real server is not).
     */
    private function withToken_(?string $token, string $method, string $uri): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];

        return $this->json($method, $uri, [], $headers);
    }

    private function registration(array $overrides = []): array
    {
        return $overrides + [
            'fullName' => 'Test Customer', 'email' => self::EMAIL,
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true,
        ];
    }

    // ---------------------------------------------------------------- register

    public function test_register_creates_customer_without_returning_a_token(): void
    {
        $this->postJson('/api/register', $this->registration())
            ->assertCreated()
            ->assertExactJson(['success' => true, 'message' => 'Customer registered successfully.']);

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertNull($user->last_login_at);              // not signed in by registering
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest('web');                            // no website session either
    }

    public function test_register_never_accepts_role_from_input(): void
    {
        $this->postJson('/api/register', $this->registration(['role' => 'admin', 'is_admin' => true]))->assertCreated();

        $this->assertSame(User::ROLE_CUSTOMER, User::where('email', self::EMAIL)->value('role'));
    }

    public function test_register_validation_and_duplicate_email(): void
    {
        $this->postJson('/api/register', $this->registration(['password_confirmation' => 'other-pass-1', 'terms' => false]))
            ->assertStatus(422)->assertJson(['success' => false])->assertJsonValidationErrors(['password', 'terms']);

        $this->customer();
        $this->postJson('/api/register', $this->registration(['email' => 'Customer@Example.TEST']))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'An account with this email already exists. Please sign in instead.']);

        $this->assertSame(1, User::count());
    }

    // ------------------------------------------------------------------- login

    public function test_login_returns_a_bearer_token_and_safe_user_fields(): void
    {
        $user = $this->customer();

        $response = $this->login(['device_name' => 'Postman'])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'token_type', 'token', 'expires_at', 'user' => ['id', 'name', 'email']])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
                'token_type' => 'Bearer',
                'user' => ['id' => $user->id, 'name' => 'Test Customer', 'email' => self::EMAIL],
            ]);

        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('user')));
        $this->assertMatchesRegularExpression('/^\d+\|eazy_[A-Za-z0-9]{40}[a-f0-9]{8}$/', $response->json('token'));
        foreach (['password', 'remember_token', 'role', 'phone', 'billing_gstin'] as $field) {
            $this->assertStringNotContainsString($field, $response->getContent());
        }
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertGuest('web');   // API login never starts a website session
    }

    public function test_only_a_hash_of_the_token_is_stored_with_customer_ability_and_7_day_expiry(): void
    {
        $this->customer();
        $plain = $this->login(['device_name' => 'Postman'])->json('token');
        [$id, $secret] = explode('|', $plain, 2);

        $row = PersonalAccessToken::findOrFail($id);
        $this->assertSame(hash('sha256', $secret), $row->token);
        $this->assertStringNotContainsString($secret, json_encode($row->getAttributes()));
        $this->assertSame([AuthController::TOKEN_ABILITY], $row->abilities);
        $this->assertSame('Postman', $row->name);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $row->expires_at->timestamp, 5);
    }

    public function test_wrong_password_and_unknown_email_get_422_and_no_token(): void
    {
        $this->customer();

        foreach ([[self::EMAIL, 'wrong-password'], ['nobody@example.test', 'wrong-password']] as [$email, $password]) {
            $this->postJson('/api/login', ['email' => $email, 'password' => $password])
                ->assertStatus(422)
                ->assertJsonMissingPath('token')
                ->assertJson(['success' => false, 'message' => LoginRequest::FAILED_MESSAGE]);
        }
        $this->postJson('/api/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_lockout_after_5_failures_returns_429_and_no_token(): void
    {
        $this->customer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->login(['password' => 'wrong-password'])->assertStatus(422);
        }

        $response = $this->login()->assertStatus(429)->assertJsonMissingPath('token');
        $this->assertStringContainsString('Too many sign-in attempts', $response->json('message'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_lockout_counter_is_shared_with_the_website_login(): void
    {
        $this->customer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['email' => self::EMAIL, 'password' => 'wrong-password']);   // website form
        }

        $this->login()->assertStatus(429);
    }

    public function test_admin_accounts_cannot_get_customer_api_tokens(): void
    {
        $admin = $this->customer();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->login()
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => AuthController::NOT_A_CUSTOMER_MESSAGE]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_closed_account_cannot_log_in(): void
    {
        $this->customer()->delete();

        $this->login()->assertStatus(422)->assertJsonMissingPath('token');
    }

    // ------------------------------------------------- token use and rejection

    public function test_user_endpoint_with_valid_token(): void
    {
        $user = $this->customer();
        $token = $this->login()->json('token');

        $this->withToken_($token, 'GET', '/api/user')
            ->assertOk()
            ->assertExactJson(['success' => true, 'user' => ['id' => $user->id, 'name' => 'Test Customer', 'email' => self::EMAIL]]);
    }

    public function test_missing_malformed_and_unknown_tokens_get_401(): void
    {
        $this->customer();
        $real = $this->login()->json('token');
        [$id] = explode('|', $real, 2);

        foreach ([null, 'not-a-token', $id.'|eazy_'.str_repeat('a', 48), '999|'.explode('|', $real, 2)[1]] as $token) {
            $this->withToken_($token, 'GET', '/api/user')
                ->assertUnauthorized()
                ->assertJson(['success' => false]);
        }

        // Without an Accept header (plain Postman request) the answer is still JSON.
        $this->app['auth']->forgetGuards();
        $this->get('/api/user')->assertUnauthorized()->assertJson(['success' => false]);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $this->customer();
        $phone = $this->login(['device_name' => 'phone'])->json('token');
        $laptop = $this->login(['device_name' => 'laptop'])->json('token');

        $this->withToken_($phone, 'POST', '/api/logout')
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Logout successful.']);

        $this->withToken_($phone, 'GET', '/api/user')->assertUnauthorized();   // revoked
        $this->withToken_($phone, 'POST', '/api/logout')->assertUnauthorized();
        $this->withToken_($laptop, 'GET', '/api/user')->assertOk();            // other device still signed in
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_token_expires_after_7_days(): void
    {
        $this->customer();
        $token = $this->login()->json('token');

        $this->travel(7)->days();
        $this->travel(-1)->minutes();
        $this->withToken_($token, 'GET', '/api/user')->assertOk();            // just before expiry

        $this->travel(2)->minutes();
        $this->withToken_($token, 'GET', '/api/user')->assertUnauthorized();  // expired
    }

    public function test_token_of_a_closed_account_is_rejected(): void
    {
        $user = $this->customer();
        $token = $this->login()->json('token');

        $user->delete();   // soft delete

        $this->withToken_($token, 'GET', '/api/user')->assertUnauthorized();
    }

    public function test_token_without_the_customer_ability_is_rejected(): void
    {
        $token = $this->customer()->createToken('other', ['something-else'])->plainTextToken;

        $this->withToken_($token, 'GET', '/api/user')->assertForbidden()->assertJson(['success' => false]);
    }

    // --------------------------------------------- website / API separation

    public function test_website_session_cannot_use_the_api(): void
    {
        $this->customer();

        $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])->assertRedirect(route('my-account'));
        $this->assertAuthenticated('web');

        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->assertAuthenticated('web');   // website session untouched
    }

    public function test_bearer_token_cannot_open_website_pages(): void
    {
        $this->customer();
        $token = $this->login()->json('token');

        $this->app['auth']->forgetGuards();
        $this->get('/my-account', ['Authorization' => 'Bearer '.$token])->assertRedirect(route('login'));
    }

    public function test_api_logout_does_not_end_the_website_session_and_vice_versa(): void
    {
        $this->customer();
        $token = $this->login()->json('token');

        $this->post('/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        $this->withToken_($token, 'POST', '/api/logout')->assertOk();
        $this->get('/my-account')->assertOk();   // website still signed in

        $token2 = $this->login()->json('token');
        $this->post('/logout')->assertRedirect(route('home'));
        $this->withToken_($token2, 'GET', '/api/user')->assertOk();   // API token still valid
    }

    public function test_api_routes_need_no_csrf_and_website_routes_keep_it(): void
    {
        $api = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/'));
        foreach ($api as $route) {
            $this->assertNotContains('web', $route->gatherMiddleware(), $route->uri());
        }

        $this->assertContains('web', Route::getRoutes()->getByName('login.submit')->gatherMiddleware());
        $this->assertContains('web', Route::getRoutes()->getByName('logout')->gatherMiddleware());
    }

    // ----------------------------------------------------------------- privacy

    /** Phase 4 auth endpoints + Phase 5 public catalogue endpoints; still nothing for orders or eSIMs. */
    public function test_only_auth_and_catalogue_endpoints_exist_so_no_orders_or_esims_are_exposed(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_starts_with($uri, 'api/'))
            ->sort()->values()->all();

        // Phase 9E adds the customer's own orders/eSIMs — only behind Bearer-token auth.
        $this->assertSame([
            'api/countries', 'api/countries/{slug}/plans', 'api/login', 'api/logout',
            'api/my-esims', 'api/my-esims/{esim}', 'api/my-orders',
            'api/plans/{slug}', 'api/register', 'api/user',
        ], $uris);

        foreach (['api/my-orders', 'api/my-esims', 'api/my-esims/{esim}'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $uri);
        }
    }

    public function test_tokens_and_passwords_are_never_logged(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            $logged[] = $e->message.' '.json_encode($e->context);
        });

        $this->customer();
        $this->login(['password' => 'wrong-password']);
        $token = $this->login()->json('token');
        $this->withToken_($token, 'GET', '/api/user');
        $this->withToken_($token, 'POST', '/api/logout');
        $this->withToken_($token, 'GET', '/api/user');

        $all = implode("\n", $logged);
        $this->assertStringNotContainsString(explode('|', $token, 2)[1], $all);
        $this->assertStringNotContainsString(self::PASSWORD, $all);
    }
}
