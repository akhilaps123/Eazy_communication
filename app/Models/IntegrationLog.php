<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Write-once record of a payment / eSIM Go / mail call. Bodies must be
 * sanitised before saving (no API keys, card data or activation codes).
 */
#[Fillable([
    'service', 'operation', 'http_method', 'url', 'status_code', 'success', 'duration_ms',
    'order_id', 'payment_id', 'provider_order_id', 'request_body', 'response_body', 'error_message',
])]
class IntegrationLog extends Model
{
    /** Logs have created_at only. */
    public const UPDATED_AT = null;

    public const SERVICE_ESIMGO = 'esimgo';

    public const SERVICE_PAYMENT = 'payment';

    public const SERVICE_MAIL = 'mail';

    /** Phase 9F: administrator actions (audit trail) — see App\Support\AdminAudit. */
    public const SERVICE_ADMIN = 'admin';

    /** Phase 9D: QR code generation for eSIMs (eSIM id + ICCID only, never activation data). */
    public const SERVICE_ESIM_QR = 'esim_qr';

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'success' => 'boolean',
            'duration_ms' => 'integer',
            'request_body' => 'array',
            'response_body' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function providerOrder(): BelongsTo
    {
        return $this->belongsTo(ProviderOrder::class);
    }
}
