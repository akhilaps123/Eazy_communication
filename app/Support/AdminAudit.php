<?php

namespace App\Support;

use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\ProviderOrder;
use App\Models\User;

/**
 * Phase 9F: audit trail of administrator actions on orders.
 *
 * No audit table existed, so — to avoid a migration — entries are stored in
 * the existing write-once integration_logs table with service = "admin":
 *   operation      the action (see ACTIONS)
 *   success        whether the action went ahead
 *   order_id / provider_order_id
 *   request_body   {admin_user_id, reason}
 *   response_body  {previous_state, new_state, note}
 *   error_message  the safe reason an action was refused
 *   created_at     when
 *
 * Only state names and short, scrubbed texts are stored — never keys,
 * tokens, passwords, activation codes, SM-DP+ addresses or gateway payloads.
 */
final class AdminAudit
{
    public const ACTIONS = [
        'reconciliation_requested' => 'Reconciliation requested',
        'reconciliation_completed' => 'Reconciliation completed',
        'reconciliation_incomplete' => 'Reconciliation incomplete',
        'retry_requested' => 'Retry requested',
        'retry_rejected' => 'Retry rejected by safety checks',
        'retry_queued' => 'Retry queued',
        'action_blocked' => 'Administrative action blocked',
        // Phase 9D (no provider call, nothing bought; never the payload itself)
        'qr_regenerated' => 'eSIM QR code regenerated',
        'qr_regeneration_failed' => 'eSIM QR code regeneration failed',
        // Phase 9E (email only; never touches fulfilment or buys anything)
        'email_retry_queued' => 'eSIM-ready email queued',
        'email_retry_rejected' => 'eSIM-ready email not queued',
    ];

    /** The order's status fields (safe to store). */
    public static function state(Order $order): array
    {
        return [
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'provider_order_status' => ProviderOrder::query()->where('order_id', $order->id)->value('status'),
        ];
    }

    public static function record(User $admin, Order $order, string $action, bool $success, ?array $previous = null, ?array $new = null, ?string $reason = null, ?string $note = null): IntegrationLog
    {
        return IntegrationLog::query()->create([
            'service' => IntegrationLog::SERVICE_ADMIN,
            'operation' => array_key_exists($action, self::ACTIONS) ? $action : 'action_blocked',
            'success' => $success,
            'order_id' => $order->id,
            'provider_order_id' => ProviderOrder::query()->where('order_id', $order->id)->value('id'),
            'request_body' => ['admin_user_id' => $admin->id, 'reason' => self::clean($reason)],
            'response_body' => ['previous_state' => $previous, 'new_state' => $new, 'note' => self::clean($note)],
            'error_message' => $success ? null : self::clean($note),
        ]);
    }

    public static function label(string $action): string
    {
        return self::ACTIONS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Short, single-line text with anything that looks like a credential or an
     * activation payload removed (admins may paste text into the reason box).
     */
    public static function clean(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
        if ($text === '') {
            return null;
        }
        $text = preg_replace([
            '/LPA:\S+/i',                                              // QR activation payloads
            '/\b(Bearer|Basic)\s+\S+/i',                               // authorization headers
            '/\b(api[_-]?key|password|secret|token|x-api-key|authorization)\b\s*[:=]\s*\S+/i',
        ], ['[removed]', '$1 [removed]', '$1=[removed]'], $text) ?? '';
        // Long key-like strings — except UUIDs (eSIM Go order references are UUIDs).
        $text = preg_replace_callback('/[A-Za-z0-9_\-]{32,}/', fn (array $m) => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $m[0]) ? $m[0] : '[removed]', $text) ?? '';

        return mb_substr($text, 0, 500);
    }
}
