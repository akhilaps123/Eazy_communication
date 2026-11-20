<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Database\DatabaseTestCase;

class RegistrationTest extends DatabaseTestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'fullName' => 'Test Customer',
            'email' => 'customer@example.test',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'terms' => 'on',
        ];
    }

    public function test_registration_page_uses_the_existing_design_and_has_a_confirmation_field(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="registrationForm"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('css/registration.css', false);
    }

    public function test_customer_can_register_and_is_signed_in(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect(route('my-account'));
        $this->assertAuthenticated();

        $user = User::where('email', 'customer@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('Test Customer', $user->name);
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->post('/register', $this->payload());

        $stored = User::first()->getAuthPassword();
        $this->assertNotSame('secret-pass-123', $stored);
        $this->assertTrue(Hash::check('secret-pass-123', $stored));
        $this->assertTrue(Hash::isHashed($stored));
    }

    public function test_customer_cannot_register_as_admin(): void
    {
        $this->post('/register', $this->payload([
            'role' => User::ROLE_ADMIN,
            'is_admin' => 1,
            'permissions' => ['*'],
        ]))->assertRedirect(route('my-account'));

        $user = User::first();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        User::factory()->create(['email' => 'customer@example.test']);

        $this->from('/register')
            ->post('/register', $this->payload(['email' => '  Customer@Example.TEST ']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'An account with this email already exists. Please sign in instead.']);

        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    public function test_email_of_a_closed_account_cannot_be_reused(): void
    {
        User::factory()->create(['email' => 'customer@example.test'])->delete();   // soft delete

        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
        $this->assertSame(1, User::withTrashed()->count());
    }

    public function test_registration_validation_rules(): void
    {
        $cases = [
            'fullName' => ['fullName' => ''],
            'email' => ['email' => 'not-an-email'],
            'password' => ['password' => 'short1', 'password_confirmation' => 'short1'],
            'terms' => ['terms' => null],
        ];

        foreach ($cases as $field => $override) {
            $this->post('/register', array_filter($this->payload($override), fn ($v) => $v !== null))
                ->assertSessionHasErrors($field);
        }

        $this->post('/register', $this->payload(['password_confirmation' => 'different-123']))
            ->assertSessionHasErrors(['password' => 'The two passwords do not match.']);

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_validation_errors_are_shown_in_the_existing_feedback_box_with_old_input(): void
    {
        $this->followingRedirects()
            ->from('/register')
            ->post('/register', $this->payload(['password_confirmation' => 'different-123']))
            ->assertSee('The two passwords do not match.')
            ->assertSee('value="customer@example.test"', false)
            ->assertSee('class="form-feedback error"', false);
    }

    public function test_signed_in_customer_is_redirected_away_from_registration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect(route('my-account'));
    }
}
