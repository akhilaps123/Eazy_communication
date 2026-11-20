<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The eSIM Go order created for a paid order (at most one per order).
 * Written only by App\Services\EsimGo\FulfilmentService (Phase 9C).
 * Statuses: pending (reserved; submitted_at NULL = never sent) → submitted
 * (bought, eSIM details pending) → completed; failed = definitely nothing
 * bought; needs_reconciliation = maybe bought, a person must check eSIM Go.
 */
#[Fillable([
    'order_id', 'provider', 'our_reference', 'provider_reference', 'status', 'attempts',
    'last_error', 'total_provider_cost', 'provider_currency', 'request_payload',
    'response_payload', 'submitted_at', 'completed_at',
])]
class ProviderOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_RECONCILIATION = 'needs_reconciliation';

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'total_provider_cost' => 'decimal:4',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function esims(): HasMany
    {
        return $this->hasMany(Esim::class);
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }
}
