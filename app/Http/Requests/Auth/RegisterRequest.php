<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the registration form (pages/registration.blade.php).
 * Fields: fullName, email, password, password_confirmation, terms.
 *
 * Only these fields are ever used. Anything else posted (e.g. "role",
 * "is_admin") is ignored — the role is set on the server.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fullName' => trim((string) $this->input('fullName')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            // Unique across all accounts, including closed (soft-deleted) ones.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'fullName.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists. Please sign in instead.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'Password must be at least :min characters.',
            'password.max' => 'Password must not be longer than :max characters.',
            'terms.accepted' => 'You must agree to the Terms of Condition and Privacy Policy.',
        ];
    }

    public function attributes(): array
    {
        return ['fullName' => 'full name'];
    }
}
