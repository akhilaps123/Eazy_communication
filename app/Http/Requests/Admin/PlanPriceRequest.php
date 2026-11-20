<?php

namespace App\Http\Requests\Admin;

use App\Models\PlanPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a (pending) price for a plan.
 *
 * The currency is always the configured display currency (USD, decision D3) and
 * is not accepted from the form. approval_status, is_active, created_by and
 * approved_by are always set by the server, never from input.
 */
class PlanPriceRequest extends FormRequest
{
    public const INTENT_PREVIEW = 'preview';

    public const INTENT_SAVE = 'save';

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'manual_price' => trim((string) $this->input('manual_price')) ?: null,
            'markup_value' => trim((string) $this->input('markup_value')) ?: null,
            'compare_at_price' => trim((string) $this->input('compare_at_price')) ?: null,
            'effective_to' => $this->input('effective_to') ?: null,
            'intent' => $this->input('intent') === self::INTENT_PREVIEW ? self::INTENT_PREVIEW : self::INTENT_SAVE,
        ]);
    }

    public function rules(): array
    {
        return [
            'pricing_method' => ['required', Rule::in([PlanPrice::METHOD_MANUAL, PlanPrice::METHOD_MARKUP_PERCENT, PlanPrice::METHOD_MARKUP_FIXED])],
            'manual_price' => ['nullable', 'required_if:pricing_method,'.PlanPrice::METHOD_MANUAL, 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'markup_value' => ['nullable', 'required_unless:pricing_method,'.PlanPrice::METHOD_MANUAL, 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'compare_at_price' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'intent' => ['required', Rule::in([self::INTENT_PREVIEW, self::INTENT_SAVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'manual_price.required_if' => 'Enter the selling price for manual pricing.',
            'manual_price.regex' => 'Enter the selling price as a number with up to 2 decimals, e.g. 19.99.',
            'markup_value.required_unless' => 'Enter the markup value.',
            'markup_value.regex' => 'Enter the markup as a number with up to 4 decimals, e.g. 25 or 2.5.',
            'compare_at_price.regex' => 'Enter the crossed-out price as a number with up to 2 decimals.',
            'effective_to.after' => 'The end date must be after the start date.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('pricing_method') === PlanPrice::METHOD_MANUAL
                && $this->input('manual_price') !== null
                && bccomp((string) $this->input('manual_price'), '0', 2) <= 0) {
                $validator->errors()->add('manual_price', 'The selling price must be greater than zero.');
            }
        }];
    }

    /** Only the rule fields (method-specific values cleared). */
    public function priceAttributes(): array
    {
        $manual = $this->input('pricing_method') === PlanPrice::METHOD_MANUAL;

        return [
            'pricing_method' => $this->input('pricing_method'),
            'manual_price' => $manual ? $this->input('manual_price') : null,
            'markup_value' => $manual ? null : $this->input('markup_value'),
            'compare_at_price' => $this->input('compare_at_price'),
            'effective_from' => $this->date('effective_from'),
            'effective_to' => $this->date('effective_to'),
        ];
    }

    public function isPreview(): bool
    {
        return $this->input('intent') === self::INTENT_PREVIEW;
    }
}
