<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A usage snapshot for an eSIM (table: esim_usage).
 */
#[Fillable([
    'esim_id', 'data_total_mb', 'data_used_mb', 'data_remaining_mb', 'is_unlimited', 'status',
    'period_start', 'period_end', 'fetched_at', 'raw_payload',
])]
class EsimUsage extends Model
{
    protected $table = 'esim_usage';

    protected function casts(): array
    {
        return [
            'data_total_mb' => 'decimal:2',
            'data_used_mb' => 'decimal:2',
            'data_remaining_mb' => 'decimal:2',
            'is_unlimited' => 'boolean',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'fetched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function esim(): BelongsTo
    {
        return $this->belongsTo(Esim::class);
    }
}
