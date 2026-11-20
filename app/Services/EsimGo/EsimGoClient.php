<?php

namespace App\Services\EsimGo;

use App\Models\IntegrationLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Minimal eSIM Go API v2.5 client using Laravel's HTTP client (no SDK package).
 * Docs: https://docs.esim-go.com/api/v2_5/
 *
 * - Auth: X-API-Key header. The key is never logged, returned or put in a URL.
 * - SSL certificate verification is always on (never disabled here).
 * - Every call is recorded in integration_logs with a summarised, key-free body.
 * - POST /orders is sent with type "validate" (no charge) — or, ONLY from
 *   purchase() and only while ESIMGO_FULFILMENT_ENABLED is on, as a real
 *   purchase (Phase 9C). Anything else is refused before a request is made.
 * - Activation details (matchingId, smdpAddress) are never logged.
 */
class EsimGoClient
{
    public const ORDER_TYPE_VALIDATE = 'validate';

    /** Real purchase — sent only by purchase(), see FulfilmentService. */
    public const ORDER_TYPE_TRANSACTION = 'transaction';

    public const BUNDLE_NAME_PATTERN = '/^[A-Za-z0-9_.:\-]{1,191}$/';

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && str_starts_with($this->baseUrl(), 'https://');
    }

    /**
     * GET /organisation — read-only; used to check the key and read the account currency.
     * Only the organisation name and currency are returned (the response also
     * contains the API key and user details, which are discarded).
     *
     * @return array{name: ?string, currency: ?string}
     */
    public function organisation(): array
    {
        $json = $this->request('get', '/organisation', [], 'organisation', fn (array $j) => self::safeOrganisation($j));

        return self::safeOrganisation($json);
    }

    /**
     * GET /catalogue — one page of bundles.
     *
     * Accepts both a bare JSON array and a {"bundles": [...], "pageCount": n} envelope.
     *
     * @return array{bundles: list<array<string, mixed>>, pageCount: ?int}
     */
    public function cataloguePage(int $page, int $perPage): array
    {
        $json = $this->request('get', '/catalogue', ['page' => $page, 'perPage' => $perPage], 'catalogue',
            fn (array $j) => ['bundles' => count(self::bundlesOf($j) ?? []), 'pageCount' => $j['pageCount'] ?? null]);

        $bundles = self::bundlesOf($json);
        if ($bundles === null) {
            throw new EsimGoException('eSIM Go returned an unexpected catalogue response.', EsimGoException::INVALID_RESPONSE);
        }

        $pageCount = isset($json['pageCount']) && is_numeric($json['pageCount']) ? (int) $json['pageCount'] : null;

        return ['bundles' => array_values(array_filter($bundles, 'is_array')), 'pageCount' => $pageCount];
    }

    /**
     * POST /orders with type "validate": checks that a bundle can be ordered and
     * returns the price. It is not a purchase: the purchase order type is never sent.
     *
     * @return array{valid: bool, total: ?string, currency: ?string, items: list<array<string, mixed>>}
     */
    public function validateBundle(string $bundleName, int $quantity = 1): array
    {
        if (! preg_match(self::BUNDLE_NAME_PATTERN, $bundleName)) {
            throw new EsimGoException('The bundle name is not valid.', EsimGoException::REQUEST);
        }
        if ($quantity < 1 || $quantity > 50) {
            throw new EsimGoException('The quantity must be between 1 and 50.', EsimGoException::REQUEST);
        }

        $json = $this->request('post', '/orders', [
            'type' => self::ORDER_TYPE_VALIDATE,
            'assign' => false,
            'order' => [[
                'type' => 'bundle',
                'quantity' => $quantity,
                'item' => $bundleName,
                'allowReassign' => false,
            ]],
        ], 'validate_order', fn (array $j) => self::safeValidation($j));

        // A validation answer never has an order reference or status; if one
        // appears, something other than a validation happened — stop loudly.
        if (isset($json['orderReference']) || isset($json['status'])) {
            throw new EsimGoException('eSIM Go answered a validation request like a real order. Stopped — check the provider account.', EsimGoException::INVALID_RESPONSE);
        }
        if (! array_key_exists('valid', $json)) {
            throw new EsimGoException('eSIM Go returned an unexpected validation response.', EsimGoException::INVALID_RESPONSE);
        }

        return self::safeValidation($json);
    }

    /**
     * POST /orders type "validate" for several lines (no purchase, no charge).
     *
     * @param  list<array{item: string, quantity: int}>  $lines
     * @param  array{order_id?: int, provider_order_id?: int}  $context  links the log entry
     * @return array{valid: bool, total: ?string, currency: ?string, items: list<array<string, mixed>>}
     */
    public function validateOrder(array $lines, array $context = []): array
    {
        $json = $this->request('post', '/orders', [
            'type' => self::ORDER_TYPE_VALIDATE,
            'assign' => false,
            'order' => self::orderLines($lines),
        ], 'validate_order', fn (array $j) => self::safeValidation($j), $context);

        if (isset($json['orderReference']) || isset($json['status'])) {
            throw new EsimGoException('eSIM Go answered a validation request like a real order. Stopped — check the provider account.', EsimGoException::INVALID_RESPONSE);
        }
        if (! array_key_exists('valid', $json)) {
            throw new EsimGoException('eSIM Go returned an unexpected validation response.', EsimGoException::INVALID_RESPONSE);
        }

        return self::safeValidation($json);
    }

    /**
     * POST /orders with ORDER_TYPE_TRANSACTION: BUYS the bundles from eSIM Go (spends
     * provider balance) and assigns each to a new eSIM.
     *
     * Only FulfilmentService calls this, after a verified payment. eSIM Go has no
     * idempotency key, so this request is NEVER retried except after HTTP 429
     * (rejected by the rate limiter, i.e. definitely not processed).
     *
     * @param  list<array{item: string, quantity: int}>  $lines
     * @param  array{order_id?: int, provider_order_id?: int}  $context
     * @return array{reference: ?string, status: ?string, status_message: ?string, total: ?string, currency: ?string, lines: list<array<string, mixed>>, esims: list<array{iccid: string, matching_id: ?string, smdp_address: ?string, item: ?string}>}
     */
    public function purchase(array $lines, array $context = []): array
    {
        if (! config('esimgo.fulfilment.enabled')) {
            throw new EsimGoException('eSIM purchasing is switched off (ESIMGO_FULFILMENT_ENABLED).', EsimGoException::NOT_CONFIGURED);
        }

        $json = $this->request('post', '/orders', [
            'type' => self::ORDER_TYPE_TRANSACTION,
            'assign' => true,
            'order' => self::orderLines($lines),
        ], 'create_order', fn (array $j) => self::safePurchaseSummary($j), $context, allowPurchase: true);

        return self::purchaseResult($json);
    }

    /**
     * GET /esims/assignments?reference=… — the eSIM install details of an order
     * (read-only; safe to repeat). Activation secrets are returned to the caller
     * only and are never written to the integration log.
     *
     * @param  array{order_id?: int, provider_order_id?: int}  $context
     * @return list<array{iccid: string, matching_id: ?string, smdp_address: ?string, profile_status: ?string}>
     */
    public function assignments(string $reference, array $context = []): array
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $reference)) {
            throw new EsimGoException('The order reference is not valid.', EsimGoException::REQUEST);
        }

        $json = $this->request('get', '/esims/assignments', ['reference' => $reference], 'esim_assignments',
            fn (array $j) => ['esims' => count(self::assignmentRows($j)), 'iccids' => array_column(self::assignmentRows($j), 'iccid')], $context);

        return self::assignmentRows($json);
    }

    /**
     * @param  callable(array): array  $summarise  key-free summary of a successful body for the log
     * @param  array{order_id?: int, provider_order_id?: int}  $context
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $data, string $operation, callable $summarise, array $context = [], bool $allowPurchase = false): array
    {
        if ($this->apiKey() === '') {
            throw new EsimGoException('eSIM Go is not configured: ESIMGO_API_KEY is empty.', EsimGoException::NOT_CONFIGURED);
        }
        if (! str_starts_with($this->baseUrl(), 'https://')) {
            throw new EsimGoException('ESIMGO_API_URL must start with https://.', EsimGoException::NOT_CONFIGURED);
        }
        // Defence in depth: POST /orders may validate; it may only BUY when called from purchase().
        if ($path === '/orders') {
            $type = $data['type'] ?? null;
            $allowed = $method === 'post' && ($type === self::ORDER_TYPE_VALIDATE || ($type === self::ORDER_TYPE_TRANSACTION && $allowPurchase));
            if (! $allowed) {
                throw new EsimGoException('Only order validation is allowed here; eSIMs are bought only by the fulfilment service.', EsimGoException::REQUEST);
            }
        }

        $maxRetries = max(0, (int) config('esimgo.retries', 2));

        for ($attempt = 0; ; $attempt++) {
            $started = microtime(true);
            $response = null;
            $error = null;

            try {
                $pending = Http::withHeaders(['X-API-Key' => $this->apiKey()])
                    ->acceptJson()
                    ->connectTimeout((int) config('esimgo.connect_timeout', 10))
                    ->timeout((int) config('esimgo.timeout', 30));
                $url = $this->baseUrl().$path;
                $response = $method === 'post' ? $pending->asJson()->post($url, $data) : $pending->get($url, $data);
            } catch (ConnectionException $e) {
                $error = 'Connection to eSIM Go failed: '.$this->redact($e->getMessage());
            }

            $this->log($operation, $method, $path, $data, $response, $error, $started, $summarise, $context);

            $status = $response?->status();
            $retryable = $response === null || $status === 429 || $status >= 500;
            // Retry reads; a POST (validation or purchase) only when it was definitely not processed (429).
            $retryAllowed = $method === 'get' || $status === 429;

            if ($response !== null && $response->successful()) {
                $json = $response->json();
                if (! is_array($json)) {
                    throw new EsimGoException('eSIM Go returned a response that is not JSON.', EsimGoException::INVALID_RESPONSE, $status);
                }

                return $json;
            }

            if ($retryable && $retryAllowed && $attempt < $maxRetries) {
                Sleep::for($this->retryDelayMs($response, $attempt))->milliseconds();

                continue;
            }

            throw $this->failure($response, $error);
        }
    }

    private function failure(?Response $response, ?string $error): EsimGoException
    {
        if ($response === null) {
            return new EsimGoException($error ?? 'Connection to eSIM Go failed.', EsimGoException::CONNECTION);
        }

        $status = $response->status();
        $message = $this->providerMessage($response);

        // Not documented by eSIM Go: recognised from the provider's own message.
        if ($status >= 400 && $status < 500 && $status !== 429 && preg_match('/balance|insufficient|funds|credit/i', $message)) {
            return new EsimGoException('eSIM Go balance is too low for this order (HTTP '.$status.').'.$message, EsimGoException::INSUFFICIENT_BALANCE, $status);
        }

        return match (true) {
            $status === 401, $status === 403 => new EsimGoException('eSIM Go rejected the API key (HTTP '.$status.'). Check ESIMGO_API_KEY and the account\'s IP whitelist.'.$message, EsimGoException::AUTHENTICATION, $status),
            $status === 429 => new EsimGoException('eSIM Go rate limit reached (HTTP 429). Try again shortly.', EsimGoException::RATE_LIMITED, $status),
            $status >= 500 => new EsimGoException('eSIM Go is temporarily unavailable (HTTP '.$status.').'.$message, EsimGoException::UNAVAILABLE, $status),
            default => new EsimGoException('eSIM Go refused the request (HTTP '.$status.').'.$message, EsimGoException::REQUEST, $status),
        };
    }

    private function retryDelayMs(?Response $response, int $attempt): int
    {
        $max = (int) config('esimgo.max_retry_wait_seconds', 10) * 1000;
        $retryAfter = $response?->header('Retry-After');
        if (is_numeric($retryAfter)) {
            return (int) min(max(0, (float) $retryAfter * 1000), $max);
        }

        return (int) min((int) config('esimgo.retry_backoff_ms', 1000) * (2 ** $attempt), $max);
    }

    /** eSIM Go errors are {"message": "..."} (sometimes text/plain). */
    private function providerMessage(Response $response): string
    {
        $json = $response->json();
        $text = is_array($json) ? (string) ($json['message'] ?? '') : (string) $response->body();
        $text = trim(mb_substr(strip_tags($this->redact($text)), 0, 200));

        return $text === '' ? '' : ' Provider message: '.$text;
    }

    private function log(string $operation, string $method, string $path, array $data, ?Response $response, ?string $error, float $started, callable $summarise, array $context = []): void
    {
        $json = $response?->json();
        $summary = null;
        if ($response !== null && is_array($json)) {
            $summary = $response->successful()
                ? $summarise($json)
                : ['message' => mb_substr($this->redact((string) ($json['message'] ?? '')), 0, 300)];
        } elseif ($response !== null && ! $response->successful()) {
            $summary = ['message' => mb_substr(strip_tags($this->redact($response->body())), 0, 300)];
        }

        IntegrationLog::query()->create([
            'service' => IntegrationLog::SERVICE_ESIMGO,
            'operation' => $operation,
            'http_method' => strtoupper($method),
            'url' => $this->baseUrl().$path.($method === 'get' && $data !== [] ? '?'.http_build_query($data) : ''),   // never the key
            'status_code' => $response?->status(),
            'success' => $response?->successful() ?? false,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'order_id' => $context['order_id'] ?? null,
            'provider_order_id' => $context['provider_order_id'] ?? null,
            'request_body' => $method === 'post' ? $data : null,
            'response_body' => $summary,
            'error_message' => $error ?? ($response !== null && ! $response->successful() ? 'HTTP '.$response->status() : null),
        ]);
    }

    /** Remove the API key from any text before it is stored or shown. */
    private function redact(string $text): string
    {
        $key = $this->apiKey();

        return $key === '' ? $text : str_replace($key, '[redacted]', $text);
    }

    /**
     * @param  list<array{item: string, quantity: int}>  $lines
     * @return list<array{type: string, quantity: int, item: string, allowReassign: bool}>
     */
    private static function orderLines(array $lines): array
    {
        if ($lines === []) {
            throw new EsimGoException('An order needs at least one bundle.', EsimGoException::REQUEST);
        }

        $out = [];
        foreach ($lines as $line) {
            $item = (string) ($line['item'] ?? '');
            $quantity = (int) ($line['quantity'] ?? 0);
            if (! preg_match(self::BUNDLE_NAME_PATTERN, $item)) {
                throw new EsimGoException('The bundle name is not valid.', EsimGoException::REQUEST);
            }
            if ($quantity < 1 || $quantity > 50) {
                throw new EsimGoException('The quantity must be between 1 and 50.', EsimGoException::REQUEST);
            }
            $out[] = ['type' => 'bundle', 'quantity' => $quantity, 'item' => $item, 'allowReassign' => false];
        }

        return $out;
    }

    /** Purchase response without activation secrets (for logs and stored payloads). */
    public static function safePurchaseSummary(array $json): array
    {
        $lines = [];
        foreach ((array) ($json['order'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $iccids = array_values(array_unique(array_merge(
                array_filter((array) ($line['iccids'] ?? []), 'is_string'),
                array_filter(array_column(array_filter((array) ($line['esims'] ?? []), 'is_array'), 'iccid'), 'is_string'),
            )));
            $lines[] = array_intersect_key($line, array_flip(['type', 'item', 'quantity', 'subTotal', 'pricePerUnit'])) + ['iccids' => $iccids];
        }

        return array_intersect_key($json, array_flip(['orderReference', 'status', 'statusMessage', 'total', 'currency', 'createdDate', 'assigned']))
            + ['order' => $lines];
    }

    private static function purchaseResult(array $json): array
    {
        $esims = [];
        foreach ((array) ($json['order'] ?? []) as $line) {
            foreach ((array) (is_array($line) ? ($line['esims'] ?? []) : []) as $esim) {
                if (is_array($esim) && is_string($esim['iccid'] ?? null) && $esim['iccid'] !== '') {
                    $esims[] = [
                        'iccid' => $esim['iccid'],
                        'matching_id' => is_string($esim['matchingId'] ?? null) ? $esim['matchingId'] : null,
                        'smdp_address' => is_string($esim['smdpAddress'] ?? null) ? $esim['smdpAddress'] : null,
                        'item' => is_string($line['item'] ?? null) ? $line['item'] : null,
                    ];
                }
            }
        }
        $summary = self::safePurchaseSummary($json);
        $reference = $json['orderReference'] ?? null;

        return [
            'reference' => is_string($reference) && $reference !== '' ? $reference : null,
            'status' => is_string($json['status'] ?? null) ? $json['status'] : null,
            'status_message' => is_string($json['statusMessage'] ?? null) ? mb_substr($json['statusMessage'], 0, 300) : null,
            'total' => is_numeric($json['total'] ?? null) ? number_format((float) $json['total'], 4, '.', '') : null,
            'currency' => is_string($json['currency'] ?? null) ? strtoupper(substr($json['currency'], 0, 3)) : null,
            'lines' => $summary['order'],
            'esims' => $esims,
        ];
    }

    /**
     * Assignments as a list (the documented example is a single object; a list or
     * an {"esims": [...]} envelope are accepted too).
     *
     * @return list<array{iccid: string, matching_id: ?string, smdp_address: ?string, profile_status: ?string}>
     */
    private static function assignmentRows(array $json): array
    {
        $rows = array_is_list($json) ? $json : (isset($json['iccid']) ? [$json] : (array) ($json['esims'] ?? $json['assignments'] ?? []));
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['iccid'] ?? null) && $row['iccid'] !== '') {
                $out[] = [
                    'iccid' => $row['iccid'],
                    'matching_id' => is_string($row['matchingId'] ?? null) ? $row['matchingId'] : null,
                    'smdp_address' => is_string($row['smdpAddress'] ?? null) ? $row['smdpAddress'] : null,
                    'profile_status' => is_string($row['profileStatus'] ?? null) ? $row['profileStatus'] : null,
                ];
            }
        }

        return $out;
    }

    /** @return list<mixed>|null */
    private static function bundlesOf(array $json): ?array
    {
        if (array_is_list($json)) {
            return $json;
        }

        return isset($json['bundles']) && is_array($json['bundles']) ? array_values($json['bundles']) : null;
    }

    /**
     * Only the organisation name and currency are kept; everything else in the
     * response (API key, users, billing and contact details, balance) is dropped.
     *
     * Accepts the documented top-level shape and the shape the live API returns:
     * {"organisations": [{...}]} — the first entry with a valid currency wins.
     *
     * @return array{name: ?string, currency: ?string}
     */
    private static function safeOrganisation(array $json): array
    {
        $organisation = $json;

        if (array_key_exists('organisations', $json)) {
            $entries = is_array($json['organisations']) ? array_values(array_filter($json['organisations'], 'is_array')) : [];
            $organisation = $entries[0] ?? [];
            foreach ($entries as $entry) {
                if (self::currencyOf($entry) !== null) {
                    $organisation = $entry;
                    break;
                }
            }
        }

        return [
            'name' => isset($organisation['name']) && is_string($organisation['name']) ? mb_substr($organisation['name'], 0, 120) : null,
            'currency' => self::currencyOf($organisation),
        ];
    }

    private static function currencyOf(array $organisation): ?string
    {
        $currency = is_string($organisation['currency'] ?? null) ? strtoupper(trim($organisation['currency'])) : '';

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }

    /** @return array{valid: bool, total: ?string, currency: ?string, items: list<array<string, mixed>>} */
    private static function safeValidation(array $json): array
    {
        $items = [];
        foreach ((array) ($json['order'] ?? []) as $item) {
            if (is_array($item)) {
                $items[] = array_intersect_key($item, array_flip(['type', 'item', 'quantity', 'subTotal', 'pricePerUnit']));
            }
        }

        return [
            'valid' => (bool) ($json['valid'] ?? false),
            'total' => isset($json['total']) && is_numeric($json['total']) ? number_format((float) $json['total'], 4, '.', '') : null,
            'currency' => isset($json['currency']) && is_string($json['currency']) ? strtoupper(substr($json['currency'], 0, 3)) : null,
            'items' => $items,
        ];
    }

    private function apiKey(): string
    {
        return trim((string) config('esimgo.api_key'));
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) config('esimgo.api_url')), '/');
    }
}
