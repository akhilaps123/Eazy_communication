<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 9E: state of a customer notification for an order (one row per order
 * and type; UNIQUE(order_id, type) prevents duplicates).
 *
 * "sent" means the mail transport ACCEPTED the message — not that it reached
 * the inbox. "sending" means it was handed to the mailer but the outcome was
 * not recorded (e.g. the worker stopped): it is never resent automatically.
 */
#[Fillable(['order_id', 'type', 'status', 'attempts', 'queued_at', 'last_attempt_at', 'sent_at', 'last_error'])]
class OrderNotification extends Model
{
    public const TYPE_ESIM_READY = 'esim_ready';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const LABELS = [
        self::STATUS_PENDING => 'Queued',
        self::STATUS_SENDING => 'Sending — result not confirmed',
        self::STATUS_SENT => 'Sent (accepted by the mail server)',
        self::STATUS_FAILED => 'Failed',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
