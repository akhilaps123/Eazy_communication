<?php

namespace App\Http\Requests\Cart;

use App\Support\BillingCountries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout form. Field names match the existing page (country, postalCode).
 * expected_total is only COMPARED with the server total — never used as a price.
 */
class CheckoutRequest extends FormRequest
{
    /** Indian GSTIN format (15 characters). Stored only — no tax is calculated. */
    public const GSTIN_PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper(trim((string) $this->input('country'))),
            'postalCode' => trim((string) $this->input('postalCode')),
            'gstin' => strtoupper(str_replace(' ', '', (string) $this->input('gstin'))) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'country' => ['required', Rule::in(array_keys(BillingCountries::all()))],
            'postalCode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]{1,19}$/'],
            'gstin' => ['nullable', 'string', 'regex:'.self::GSTIN_PATTERN],
            'expected_total' => ['required', 'regex:/^\d{1,10}\.\d{2}$/'],
            'checkout_token' => ['required', 'string', 'size:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'country.required' => 'Choose your billing country.',
            'country.in' => 'Choose your billing country from the list.',
            'postalCode.required' => 'Enter your postal code.',
            'postalCode.max' => 'The postal code is too long.',
            'postalCode.regex' => 'Enter a valid postal code (letters, numbers, spaces or hyphens).',
            'gstin.regex' => 'Enter a valid 15-character GST number, e.g. 22AAAAA0000A1Z5.',
            'expected_total.*' => 'Please reload the checkout page and try again.',
            'checkout_token.*' => 'Your checkout session expired. Please review your order and try again.',
        ];
    }

    /** @return array{billing_country: string, billing_postal_code: string, billing_gstin: ?string} */
    public function billing(): array
    {
        return [
            'billing_country' => $this->input('country'),
            'billing_postal_code' => $this->input('postalCode'),
            'billing_gstin' => $this->input('gstin'),
        ];
    }
}
