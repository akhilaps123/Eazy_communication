<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A purchased plan with its price snapshot. Snapshot columns cannot be
 * changed after creation, so historical orders stay exactly as sold.
 */
#[Fillable([
    'order_id', 'plan_id', 'plan_name', 'bundle_code', 'unit_price', 'quantity', 'discount_amount',
    'tax_amount', 'line_total', 'unit_provider_cost', 'provider_currency', 'trip_start_date',
])]
class OrderItem extends Model
{
    /** Columns frozen once the item exists. */
    public const SNAPSHOT_COLUMNS = [
        'order_id', 'plan_id', 'plan_name', 'bundle_code', 'unit_price', 'quantity',
        'discount_amount', 'tax_amount', 'line_total', 'unit_provider_cost', 'provider_currency',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_provider_cost' => 'decimal:4',
            'trip_start_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (OrderItem $item) {
            if ($item->isDirty(self::SNAPSHOT_COLUMNS)) {
                throw new LogicException('Order item price snapshots cannot be changed after the order is created.');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function esims(): HasMany
    {
        return $this->hasMany(Esim::class);
    }
}
