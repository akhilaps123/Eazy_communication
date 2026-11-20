<?php

namespace App\Http\Requests\Cart;

use App\Services\CartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a plan to the cart. Only the plan slug, quantity and optional trip date
 * are accepted — never a price (prices are always calculated on the server).
 */
class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $date = trim((string) $this->input('trip_start_date'));
        // The country page's date picker shows dd-mm-yyyy; accept it and normalise.
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
            $date = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $this->merge([
            'plan' => trim((string) $this->input('plan')),
            'quantity' => $this->input('quantity', 1),
            'trip_start_date' => $date !== '' ? $date : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
            'trip_start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Choose a plan first.',
            'quantity.integer' => 'Quantity must be a whole number from 1 to '.CartService::MAX_QUANTITY.'.',
            'quantity.min' => 'Quantity must be a whole number from 1 to '.CartService::MAX_QUANTITY.'.',
            'quantity.max' => 'Quantity must be a whole number from 1 to '.CartService::MAX_QUANTITY.'.',
            'trip_start_date.date_format' => 'Choose a valid trip start date.',
            'trip_start_date.after_or_equal' => 'The trip start date cannot be in the past.',
            'trip_start_date.before_or_equal' => 'The trip start date must be within the next 12 months.',
        ];
    }
}
