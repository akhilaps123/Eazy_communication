<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Discount code. Validation rules (dates, limits, minimums) are applied on the
 * server at checkout in a later phase — never in browser JavaScript.
 */
#[Fillable([
    'code', 'description', 'discount_type', 'discount_value', 'currency', 'min_order_amount',
    'max_discount_amount', 'starts_at', 'ends_at', 'max_redemptions', 'max_redemptions_per_user', 'is_active',
])]
class PromoCode extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_redemptions' => 'integer',
            'max_redemptions_per_user' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
