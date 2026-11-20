<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and authenticates the login form (pages/login.blade.php)
 * and the API login (POST /api/login).
 * Fields: email, password, remember.
 *
 * Rate limit: 5 failed attempts per email + IP address, then a lockout
 * (shown as a normal form message, not an error page). The website and the
 * API share the same counter.
 */
class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const FAILED_MESSAGE = 'The email or password you entered is incorrect.';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }

    /**
     * Website login: signs the customer in to the session ("web") guard.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Soft-deleted (closed) accounts are excluded automatically by the User model.
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->failed();
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * API login: checks the email and password WITHOUT starting a website
     * session. Same lockout, same error message as the website.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $provider = Auth::guard('web')->getProvider();

        // Soft-deleted (closed) accounts are excluded automatically by the User model.
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            $this->failed();
        }

        $provider->rehashPasswordIfRequired($user, $credentials);
        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Same message for unknown email and wrong password (no account enumeration).
     *
     * @throws ValidationException
     */
    protected function failed(): never
    {
        RateLimiter::hit($this->throttleKey(), 60);

        throw ValidationException::withMessages(['email' => self::FAILED_MESSAGE]);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        // Status 429 only affects JSON responses; browsers are still redirected
        // back to the form with this message.
        throw ValidationException::withMessages([
            'email' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
        ])->status(429);
    }

    public function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->lower().'|'.$this->ip());
    }
}
