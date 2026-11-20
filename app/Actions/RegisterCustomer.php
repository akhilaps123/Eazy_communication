<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Creates a customer account. Shared by the website (RegisterController) and
 * the API (Api\AuthController) so both always apply the same rules.
 *
 * Expects data already validated by App\Http\Requests\Auth\RegisterRequest.
 * The role is always "customer", set here on the server — never from input.
 */
class RegisterCustomer
{
    public const DUPLICATE_MESSAGE = 'An account with this email already exists. Please sign in instead.';

    /**
     * @param  array{fullName: string, email: string, password: string}  $data
     * @param  bool  $signingIn  true when the customer is signed in straight away (website)
     */
    public function handle(array $data, bool $signingIn): User
    {
        $user = new User;
        $user->name = $data['fullName'];
        $user->email = $data['email'];
        $user->password = $data['password'];   // hashed by the model's "hashed" cast
        $user->role = User::ROLE_CUSTOMER;     // always set by the server, never from input
        $user->last_login_at = $signingIn ? now() : null;

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // Two simultaneous sign-ups with the same email: the database unique key wins.
            throw ValidationException::withMessages(['email' => self::DUPLICATE_MESSAGE]);
        }

        event(new Registered($user));

        return $user;
    }
}
