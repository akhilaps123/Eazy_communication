<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer order with a totals/billing snapshot (planning report B4, H3.10).
 * Payment and fulfilment are tracked in two separate status fields.
 */
#[Fillable([
    'order_number', 'user_id', 'currency', 'subtotal', 'discount_total', 'tax_total', 'total',
    'tax_rate', 'promo_code_id', 'promo_code', 'payment_status', 'fulfillment_status',
    'billing_name', 'billing_email', 'billing_country', 'billing_postal_code', 'billing_gstin',
    'expires_at', 'paid_at', 'fulfilled_at', 'cancelled_at',
])]
class Order extends Model
{
    public const PAYMENT_PENDING = 'pending_payment';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_CANCELLED = 'cancelled';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    public const FULFILLMENT_NOT_STARTED = 'not_started';

    public const FULFILLMENT_PROCESSING = 'processing';

    public const FULFILLMENT_FULFILLED = 'fulfilled';

    public const FULFILLMENT_NEEDS_ATTENTION = 'needs_attention';

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** At most one provider order per order (duplicate-purchase guard). */
    public function providerOrder(): HasOne
    {
        return $this->hasOne(ProviderOrder::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function esims(): HasManyThrough
    {
        return $this->hasManyThrough(Esim::class, OrderItem::class);
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }

    /** Phase 9E: the "eSIM ready" email state (at most one per order). */
    public function esimReadyNotification(): HasOne
    {
        return $this->hasOne(OrderNotification::class)->where('type', OrderNotification::TYPE_ESIM_READY);
    }
}
