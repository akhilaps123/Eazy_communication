<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Database\DatabaseTestCase;

/**
 * Notifications are faked: no email is sent and no reset link is logged.
 */
class PasswordResetTest extends DatabaseTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = User::factory()->create(['email' => 'customer@example.test', 'password' => 'old-password-1']);
    }

    private function reset(array $overrides = [])
    {
        // Only create a token when the test did not supply one: creating a token
        // replaces any earlier token for the same email.
        $overrides['token'] ??= Password::createToken($this->user);

        return $this->from('/reset-password/x')->post('/reset-password', $overrides + [
            'email' => 'customer@example.test',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);
    }

    public function test_reset_link_is_sent_to_a_registered_email(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['identity' => 'Customer@Example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', ForgotPasswordController::STATUS_MESSAGE);

        Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
            $url = $notification->toMail($this->user)->actionUrl;

            return str_starts_with($url, url('/reset-password/'.$notification->token))
                && str_contains($url, 'email=customer%40example.test');
        });
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'customer@example.test']);
    }

    public function test_unknown_email_gets_the_same_response_and_no_email(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['identity' => 'nobody@example.test'])
            ->assertSessionHas('status', ForgotPasswordController::STATUS_MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->post('/forgot-password', ['identity' => ''])->assertSessionHasErrors('identity');
        $this->post('/forgot-password', ['identity' => 'just-a-username'])->assertSessionHasErrors('identity');
        Notification::assertNothingSent();
    }

    public function test_stored_reset_token_is_hashed_not_plain(): void
    {
        $plain = Password::createToken($this->user);
        $stored = DB::table('password_reset_tokens')->where('email', 'customer@example.test')->value('token');

        $this->assertNotSame($plain, $stored);
        $this->assertTrue(Hash::check($plain, $stored));
    }

    public function test_reset_page_renders_with_token_and_email(): void
    {
        $this->get('/reset-password/some-token?email=customer%40example.test')
            ->assertOk()
            ->assertSee('value="some-token"', false)
            ->assertSee('value="customer@example.test"', false)
            ->assertSee('css/forgot-password.css', false);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $rememberBefore = $this->user->remember_token;

        $this->reset()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertNotSame($rememberBefore, $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'customer@example.test']);

        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'new-password-1']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Password::createToken($this->user);

        $this->reset(['token' => 'not-the-real-token'])
            ->assertSessionHasErrors(['email' => ResetPasswordController::INVALID_MESSAGE]);

        $this->assertTrue(Hash::check('old-password-1', $this->user->fresh()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = Password::createToken($this->user);

        // Control: the same token is still valid just before expiry...
        $this->travel(59)->minutes();
        $this->assertTrue(Password::tokenExists($this->user, $token));

        // ...and rejected once the 60-minute lifetime has passed.
        $this->travel(2)->minutes();

        $this->reset(['token' => $token])
            ->assertSessionHasErrors(['email' => ResetPasswordController::INVALID_MESSAGE]);
        $this->assertTrue(Hash::check('old-password-1', $this->user->fresh()->password));
    }

    public function test_token_cannot_be_reused(): void
    {
        $token = Password::createToken($this->user);

        $this->reset(['token' => $token])->assertRedirect(route('login'));
        $this->reset(['token' => $token, 'password' => 'third-password-1', 'password_confirmation' => 'third-password-1'])
            ->assertSessionHasErrors(['email' => ResetPasswordController::INVALID_MESSAGE]);

        $this->assertTrue(Hash::check('new-password-1', $this->user->fresh()->password));
    }

    public function test_token_for_another_email_is_rejected(): void
    {
        $token = Password::createToken($this->user);
        User::factory()->create(['email' => 'other@example.test']);

        $this->reset(['token' => $token, 'email' => 'other@example.test'])
            ->assertSessionHasErrors(['email' => ResetPasswordController::INVALID_MESSAGE]);
    }

    public function test_new_password_is_validated(): void
    {
        $this->reset(['password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->reset(['password_confirmation' => 'something-else-1'])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-1', $this->user->fresh()->password));
    }
}
