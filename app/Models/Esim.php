<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A purchased eSIM.
 *
 * SECURITY: smdp_address and activation_code are encrypted at rest with
 * APP_KEY ("encrypted" cast) and hidden from array/JSON output. Changing or
 * losing APP_KEY makes them unreadable. Always check isOwnedBy() before
 * showing QR or activation details.
 */
#[Fillable([
    'user_id', 'order_item_id', 'provider_order_id', 'iccid', 'smdp_address', 'activation_code',
    'qr_code_path', 'bundle_code', 'status', 'activated_at', 'expires_at', 'last_usage_synced_at',
])]
#[Hidden(['smdp_address', 'activation_code', 'qr_code_path'])]
class Esim extends Model
{
    protected function casts(): array
    {
        return [
            'smdp_address' => 'encrypted',
            'activation_code' => 'encrypted',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_usage_synced_at' => 'datetime',
        ];
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->getKey();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function providerOrder(): BelongsTo
    {
        return $this->belongsTo(ProviderOrder::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(EsimUsage::class);
    }

    /** The most recent usage snapshot. */
    public function latestUsage(): HasOne
    {
        return $this->hasOne(EsimUsage::class)->latestOfMany('fetched_at');
    }
}
