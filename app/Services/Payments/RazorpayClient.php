<?php

namespace App\Services\Payments;

use App\Models\IntegrationLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal server-side Razorpay API client (TEST MODE ONLY), using Laravel's
 * HTTP client — no SDK package. Docs: https://razorpay.com/docs/api/
 *
 * The key secret is only used for HTTP Basic auth and HMAC signatures here;
 * it is never logged, returned or sent to the browser. Calls are recorded in
 * integration_logs without credentials, signatures or card details.
 */
class RazorpayClient
{
    /** Configured for test-mode payments? (keys present, mode test, not a live key) */
    public function isConfigured(): bool
    {
        $keyId = (string) config('razorpay.key_id');

        return config('razorpay.mode') === 'test'
            && $keyId !== ''
            && (string) config('razorpay.key_secret') !== ''
            && ! str_starts_with($keyId, 'rzp_live_');
    }

    public function keyId(): string
    {
        return (string) config('razorpay.key_id');
    }

    /**
     * POST /v1/orders — amount in the currency's smallest unit.
     *
     * @return array<string, mixed>
     */
    public function createOrder(int $amountMinor, string $currency, string $receipt, array $notes, ?int $orderId = null, ?int $paymentId = null): array
    {
        return $this->send('post', '/orders', 'create_order', [
            'amount' => $amountMinor,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $notes,
        ], $orderId, $paymentId);
    }

    /**
     * GET /v1/payments/{id}
     *
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId, ?int $orderId = null, ?int $paymentRowId = null): array
    {
        if (! preg_match('/^pay_[A-Za-z0-9]{6,40}$/', $paymentId)) {
            throw new PaymentException('The payment reference is not valid.');
        }

        return $this->send('get', '/payments/'.$paymentId, 'fetch_payment', null, $orderId, $paymentRowId);
    }

    /** Checkout signature: HMAC-SHA256(server order id + "|" + payment id, key secret). */
    public function isValidPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        $secret = (string) config('razorpay.key_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $gatewayOrderId.'|'.$paymentId, $secret), $signature);
    }

    /** Webhook signature: HMAC-SHA256(raw request body, webhook secret). */
    public function isValidWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('razorpay.webhook_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /** Payment entity fields worth keeping (no card, VPA, contact or bank details). */
    public static function sanitisePayment(array $payment): array
    {
        return array_intersect_key($payment, array_flip([
            'id', 'entity', 'amount', 'currency', 'status', 'order_id', 'method', 'captured',
            'international', 'amount_refunded', 'refund_status', 'error_code', 'error_description',
            'error_source', 'error_step', 'error_reason', 'created_at',
        ]));
    }

    private function send(string $method, string $path, string $operation, ?array $body, ?int $orderId, ?int $paymentId): array
    {
        if (! $this->isConfigured()) {
            throw new PaymentException('Online payment is not available yet.', 503);
        }

        $started = microtime(true);
        $response = null;
        $error = null;

        try {
            $request = Http::withBasicAuth($this->keyId(), (string) config('razorpay.key_secret'))
                ->acceptJson()
                ->timeout((int) config('razorpay.timeout', 15));
            $url = rtrim((string) config('razorpay.api_url'), '/').$path;
            $response = $method === 'post' ? $request->asJson()->post($url, $body ?? []) : $request->get($url);
        } catch (ConnectionException) {
            $error = 'Connection to the payment gateway failed.';
        }

        $this->log($operation, strtoupper($method), $path, $body, $response, $error, $started, $orderId, $paymentId);

        if ($response === null) {
            throw new PaymentException('The payment gateway could not be reached. Please try again in a moment.', 502);
        }
        if (! $response->successful()) {
            throw new PaymentException($this->gatewayMessage($response), $response->status() >= 500 ? 502 : 422);
        }

        return (array) $response->json();
    }

    /** A customer-safe message for a gateway error (Razorpay's own description, no internals). */
    private function gatewayMessage(Response $response): string
    {
        $description = (string) data_get($response->json(), 'error.description', '');
        $description = mb_substr(strip_tags($description), 0, 200);

        return $description !== ''
            ? 'The payment gateway declined the request: '.$description
            : 'The payment gateway declined the request. Please try again later.';
    }

    private function log(string $operation, string $method, string $path, ?array $body, ?Response $response, ?string $error, float $started, ?int $orderId, ?int $paymentId): void
    {
        $json = $response?->json();

        IntegrationLog::query()->create([
            'service' => IntegrationLog::SERVICE_PAYMENT,
            'operation' => 'razorpay_'.$operation,
            'http_method' => $method,
            'url' => rtrim((string) config('razorpay.api_url'), '/').$path,   // no credentials in URLs
            'status_code' => $response?->status(),
            'success' => $response?->successful() ?? false,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'request_body' => $body,
            'response_body' => is_array($json)
                ? (($json['entity'] ?? null) === 'payment' ? self::sanitisePayment($json) : array_intersect_key($json, array_flip(['id', 'entity', 'amount', 'amount_paid', 'amount_due', 'currency', 'receipt', 'status', 'attempts', 'created_at', 'error'])))
                : null,
            'error_message' => $error,
        ]);
    }
}
