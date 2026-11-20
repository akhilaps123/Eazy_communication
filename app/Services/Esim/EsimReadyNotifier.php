<?php

namespace App\Services\Esim;

use App\Jobs\SendEsimReadyEmail;
use App\Mail\EsimReadyMail;
use App\Models\Esim;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\ProviderOrder;
use App\Support\CustomerEsims;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Phase 9E — the "Your eSIM is ready" email (one per order).
 *
 * Sent only when every condition holds (blockingReason() = null):
 *   1. the order is paid with a SERVER-verified, captured payment, not cancelled;
 *   2. fulfilment is complete (order fulfilled, eSIM Go order completed);
 *   3. all ordered eSIMs are stored;
 *   4. each has valid activation details (ActivationPayload);
 *   5. each has a generated QR code.
 *
 * Duplicates: order_notifications has UNIQUE(order_id, type). queueIfReady()
 * inserts the row with insertOrIgnore — only the call that creates it queues
 * the job. The job sends only from "pending" (or "failed" during the queue's
 * own retries), under a row lock, and marks "sending" before handing the
 * message to the mailer, so a repeated job, webhook or reconciliation never
 * sends twice. An outcome that was not recorded stays "sending" and is never
 * resent automatically (an admin decides).
 *
 * Nothing here touches the order, payment, provider order or eSIM status, and
 * nothing here can buy an eSIM. The email contains no activation data: the
 * customer signs in to see the QR code.
 */
class EsimReadyNotifier
{
    public const RESULT_QUEUED = 'queued';

    public const RESULT_NOT_READY = 'not_ready';

    public const RESULT_ALREADY = 'already';

    public const RESULT_UNAVAILABLE = 'unavailable';

    public function __construct(private readonly EsimQrService $qr) {}

    /** The order_notifications table exists (the Phase 9E migration has been run). */
    public static function trackingAvailable(): bool
    {
        try {
            return Schema::hasTable('order_notifications');
        } catch (Throwable) {
            return false;
        }
    }

    public function notification(Order $order): ?OrderNotification
    {
        return self::trackingAvailable()
            ? OrderNotification::query()->where('order_id', $order->id)->where('type', OrderNotification::TYPE_ESIM_READY)->first()
            : null;
    }

    /** Why the email may not be sent for this order (null = it may). Read-only; no provider call. */
    public function blockingReason(Order $order): ?string
    {
        if (! self::trackingAvailable()) {
            return 'Email tracking is not set up yet: the Phase 9E migration (order_notifications) has not been run.';
        }
        $order = Order::query()->with(['items', 'providerOrder', 'user'])->find($order->id);
        if ($order === null) {
            return 'The order no longer exists.';
        }
        if ($order->payment_status !== Order::PAYMENT_PAID || $order->paid_at === null || $order->cancelled_at !== null) {
            return 'The order is not paid.';
        }
        if (! CustomerEsims::isDelivered($order)) {
            return $order->fulfillment_status !== Order::FULFILLMENT_FULFILLED
                ? 'The order is not fulfilled yet.'
                : 'No server-verified, captured payment was found for this order.';
        }
        if ($order->providerOrder?->status !== ProviderOrder::STATUS_COMPLETED) {
            return 'The eSIM Go order is not completed.';
        }

        $esims = Esim::query()->whereIn('order_item_id', $order->items->pluck('id'))->orderBy('id')->get();
        $expected = (int) $order->items->sum('quantity');
        if ($expected < 1 || $esims->count() < $expected) {
            return "Only {$esims->count()} of {$expected} eSIM(s) are stored.";
        }
        foreach ($esims as $esim) {
            try {
                $this->qr->payload($esim);
            } catch (InvalidActivationData $e) {
                return "eSIM {$esim->iccid}: activation details are incomplete ({$e->getMessage()})";
            }
            if ($this->qr->image($esim) === null) {
                return "eSIM {$esim->iccid}: the QR code is not available yet.";
            }
        }
        if (! filter_var((string) $order->user?->email, FILTER_VALIDATE_EMAIL)) {
            return 'The customer account has no valid email address.';
        }

        return null;
    }

    /**
     * Queue the email once, if the order is ready. Safe to call any number of times.
     *
     * @return array{result: string, message: string}
     */
    public function queueIfReady(Order $order, string $trigger): array
    {
        if ($reason = $this->blockingReason($order)) {
            return ['result' => self::trackingAvailable() ? self::RESULT_NOT_READY : self::RESULT_UNAVAILABLE, 'message' => $reason];
        }

        $now = now();
        $created = OrderNotification::query()->insertOrIgnore([
            'order_id' => $order->id, 'type' => OrderNotification::TYPE_ESIM_READY, 'status' => OrderNotification::STATUS_PENDING,
            'attempts' => 0, 'queued_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        if ($created === 0) {
            return ['result' => self::RESULT_ALREADY, 'message' => 'The eSIM-ready email was already queued or sent for this order.'];
        }

        $notification = $this->notification($order);
        $this->log($order, 'esim_ready_email_queued', true, "eSIM-ready email queued ({$trigger}).");
        SendEsimReadyEmail::dispatch($notification->id)->afterCommit();

        return ['result' => self::RESULT_QUEUED, 'message' => 'The eSIM-ready email has been queued.'];
    }

    /**
     * Administrator retry of a FAILED (or unconfirmed "sending") email, or a first
     * send when none was queued. Never touches fulfilment and never buys anything.
     *
     * @return array{result: string, message: string}
     */
    public function retry(Order $order): array
    {
        if ($reason = $this->blockingReason($order)) {
            return ['result' => self::trackingAvailable() ? self::RESULT_NOT_READY : self::RESULT_UNAVAILABLE, 'message' => $reason];
        }
        $existing = $this->notification($order);
        if ($existing === null) {
            return $this->queueIfReady($order, 'admin');
        }

        $reset = OrderNotification::query()->whereKey($existing->id)
            ->whereIn('status', [OrderNotification::STATUS_FAILED, OrderNotification::STATUS_SENDING])
            ->update(['status' => OrderNotification::STATUS_PENDING, 'queued_at' => now(), 'updated_at' => now()]);
        if ($reset === 0) {
            return ['result' => self::RESULT_ALREADY, 'message' => $existing->status === OrderNotification::STATUS_SENT
                ? 'The eSIM-ready email was already sent for this order; it is not sent twice.'
                : 'The eSIM-ready email is already queued.'];
        }

        $this->log($order, 'esim_ready_email_queued', true, 'eSIM-ready email re-queued by an administrator.');
        SendEsimReadyEmail::dispatch($existing->id)->afterCommit();

        return ['result' => self::RESULT_QUEUED, 'message' => 'The eSIM-ready email has been queued again.'];
    }

    /**
     * Send one queued email (called by SendEsimReadyEmail). Throws when the mailer
     * fails so the queue can retry; the row is marked "failed" first.
     */
    public function deliver(int $notificationId): void
    {
        $notification = DB::transaction(function () use ($notificationId) {
            $row = OrderNotification::query()->lockForUpdate()->find($notificationId);
            if ($row === null || ! in_array($row->status, [OrderNotification::STATUS_PENDING, OrderNotification::STATUS_FAILED], true)) {
                return null;   // already sent, being sent, or gone: never send twice
            }
            $row->forceFill(['status' => OrderNotification::STATUS_SENDING, 'attempts' => $row->attempts + 1, 'last_attempt_at' => now()])->save();

            return $row;
        });
        if ($notification === null) {
            return;
        }

        $order = Order::query()->with(['user', 'items'])->find($notification->order_id);
        if ($reason = $order === null ? 'The order no longer exists.' : $this->blockingReason($order)) {
            $this->markFailed($notification, 'Not sent: '.$reason);

            return;
        }

        try {
            Mail::to($order->user->email, $order->user->name)->send(new EsimReadyMail($order));
        } catch (Throwable $e) {
            $this->markFailed($notification, 'The mail server did not accept the message ('.class_basename($e).').');

            throw $e;
        }

        $notification->forceFill(['status' => OrderNotification::STATUS_SENT, 'sent_at' => now(), 'last_error' => null])->save();
        $this->log($order, 'esim_ready_email_sent', true, 'eSIM-ready email accepted by the mail transport.');
    }

    public function markFailed(OrderNotification $notification, string $reason): void
    {
        $notification->forceFill(['status' => OrderNotification::STATUS_FAILED, 'last_error' => mb_substr($reason, 0, 500)])->save();
        if ($order = Order::query()->find($notification->order_id)) {
            $this->log($order, 'esim_ready_email_failed', false, $reason);
        }
    }

    private function log(Order $order, string $operation, bool $success, string $message): void
    {
        IntegrationLog::query()->create([
            'service' => IntegrationLog::SERVICE_MAIL,
            'operation' => $operation,
            'success' => $success,
            'order_id' => $order->id,
            'request_body' => ['note' => mb_substr($message, 0, 300)],
            'error_message' => $success ? null : mb_substr($message, 0, 500),
        ]);
    }
}
