<?php

namespace App\Http\Requests\Cart;

use App\Services\CartService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        $message = 'Quantity must be a whole number from 1 to '.CartService::MAX_QUANTITY.'.';

        return ['quantity.required' => $message, 'quantity.integer' => $message, 'quantity.min' => $message, 'quantity.max' => $message];
    }
}
