<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RazorpayClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/razorpay (Phase 8, TEST MODE). No session or CSRF — the request
 * is authenticated by X-Razorpay-Signature = HMAC-SHA256(raw body, webhook secret).
 *
 * Unsigned or wrongly signed requests are rejected (400) and never stored.
 * Signed events are stored once in webhook_events (unique gateway + event id from
 * the x-razorpay-event-id header); duplicates are acknowledged without re-processing.
 * Status changes go through PaymentService (locked, idempotent, never downgrades a
 * captured payment), so out-of-order and repeated deliveries are safe.
 */
class RazorpayWebhookController extends Controller
{
    private const HANDLED_EVENTS = ['payment.captured', 'payment.failed', 'payment.authorized', 'order.paid'];

    public function __construct(
        private readonly RazorpayClient $razorpay,
        private readonly PaymentService $payments,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ((string) config('razorpay.webhook_secret') === '') {
            return response()->json(['status' => 'not_configured'], 503);   // Razorpay retries later
        }

        $raw = $request->getContent();   // exact raw body (do not re-encode)
        if (! $this->razorpay->isValidWebhookSignature($raw, (string) $request->header('X-Razorpay-Signature'))) {
            Log::warning('Rejected Razorpay webhook with an invalid signature.', ['ip' => $request->ip()]);   // no body, no headers

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload) || ! is_string($payload['event'] ?? null)) {
            return response()->json(['status' => 'invalid_payload'], 400);
        }

        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: 'sha256:'.hash('sha256', $raw));
        $eventType = $payload['event'];

        $event = WebhookEvent::query()->where('gateway', Payment::GATEWAY_RAZORPAY)->where('event_id', $eventId)->first();
        if ($event && in_array($event->processing_status, [WebhookEvent::STATUS_PROCESSED, WebhookEvent::STATUS_IGNORED], true)) {
            return response()->json(['status' => 'duplicate']);
        }

        if ($event === null) {
            try {
                $event = WebhookEvent::query()->create([
                    'gateway' => Payment::GATEWAY_RAZORPAY,
                    'event_id' => mb_substr($eventId, 0, 191),
                    'event_type' => mb_substr($eventType, 0, 100),
                    'signature_valid' => true,
                    'payload' => $this->sanitisedPayload($payload),
                    'processing_status' => WebhookEvent::STATUS_RECEIVED,
                ]);
            } catch (UniqueConstraintViolationException) {
                return response()->json(['status' => 'duplicate']);   // the same event arrived twice at once
            }
        }

        return $this->process($event, $payload, $eventType);
    }

    private function process(WebhookEvent $event, array $payload, string $eventType): JsonResponse
    {
        if (! in_array($eventType, self::HANDLED_EVENTS, true)) {
            return $this->finish($event, WebhookEvent::STATUS_IGNORED, 'Event type not handled.');
        }

        $entity = data_get($payload, 'payload.payment.entity');
        if (! is_array($entity) || ! is_string($entity['order_id'] ?? null)) {
            return $this->finish($event, WebhookEvent::STATUS_IGNORED, 'No payment entity with an order id.');
        }

        // Only a payment record we created ourselves can be matched (no other order can be touched).
        $payment = Payment::query()
            ->where('gateway', Payment::GATEWAY_RAZORPAY)
            ->where('gateway_order_id', $entity['order_id'])
            ->first();
        if ($payment === null) {
            return $this->finish($event, WebhookEvent::STATUS_IGNORED, 'No matching payment record.');
        }
        $event->payment_id = $payment->id;

        try {
            $this->payments->applyGatewayPayment($payment, $entity, 'webhook:'.$eventType);
        } catch (PaymentException $e) {
            // Amount / currency / order mismatch: nothing changed. Retrying would not help.
            return $this->finish($event, WebhookEvent::STATUS_FAILED, $e->getMessage());
        }

        return $this->finish($event, WebhookEvent::STATUS_PROCESSED, null);
    }

    private function finish(WebhookEvent $event, string $status, ?string $message): JsonResponse
    {
        $event->fill(['processing_status' => $status, 'processed_at' => now(), 'error_message' => $message])->save();

        return response()->json(['status' => $status]);
    }

    /** Keep only the fields we use (never card, VPA, bank or contact details). */
    private function sanitisedPayload(array $payload): array
    {
        $clean = array_intersect_key($payload, array_flip(['entity', 'account_id', 'event', 'contains', 'created_at']));
        if (is_array($p = data_get($payload, 'payload.payment.entity'))) {
            $clean['payload']['payment']['entity'] = RazorpayClient::sanitisePayment($p);
        }
        if (is_array($o = data_get($payload, 'payload.order.entity'))) {
            $clean['payload']['order']['entity'] = array_intersect_key($o, array_flip(['id', 'entity', 'amount', 'amount_paid', 'amount_due', 'currency', 'receipt', 'status', 'created_at']));
        }

        return $clean;
    }
}
