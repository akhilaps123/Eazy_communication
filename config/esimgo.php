<?php

/*
|--------------------------------------------------------------------------
| eSIM Go provider (Phase 9A) — connection, catalogue sync, order VALIDATION
|--------------------------------------------------------------------------
| All credentials come from .env (config:cache safe). The API key is only
| sent in the X-API-Key header: never logged, never shown, never in URLs.
| Docs: https://docs.esim-go.com/api/v2_5/
|
| Phase 9A never places real provider orders: the client only sends
| POST /orders with type "validate" (see EsimGoClient::validateBundle).
*/

return [

    // Must be https:// — requests are refused otherwise (SSL verification is always on).
    'api_url' => env('ESIMGO_API_URL', 'https://api.esim-go.com/v2.5'),

    'api_key' => env('ESIMGO_API_KEY'),

    /*
    | Currency of catalogue prices. eSIM Go prices are in the organisation's
    | currency; leave empty to read it from GET /organisation during a sync.
    */
    'currency' => env('ESIMGO_CURRENCY'),

    // Seconds.
    'connect_timeout' => 10,
    'timeout' => 30,

    /*
    | Retries for 429 (rate limited, honouring Retry-After) and for connection
    | errors / 5xx on read-only GET requests. eSIM Go allows ~10 requests per
    | second per IP.
    */
    'retries' => 2,
    'retry_backoff_ms' => 1000,
    'max_retry_wait_seconds' => 10,

    /*
    | Fulfilment (Phase 9C): buying the eSIM after a verified payment.
    |
    | OFF by default. When off, paid orders stay "not_started" and nothing is
    | bought. Payments made while Razorpay is in TEST mode never trigger a real
    | purchase unless ESIMGO_FULFILMENT_ALLOW_TEST_PAYMENTS=true is set on
    | purpose (a supervised test that spends real eSIM Go balance).
    */
    'fulfilment' => [
        'enabled' => (bool) env('ESIMGO_FULFILMENT_ENABLED', false),
        'allow_test_payments' => (bool) env('ESIMGO_FULFILMENT_ALLOW_TEST_PAYMENTS', false),
        // Fetching eSIM details is read-only and may be repeated; buying never is.
        'assignment_checks' => 5,
        'assignment_retry_seconds' => 120,
        'lock_seconds' => 300,
    ],

    'catalogue' => [
        'per_page' => 100,
        'max_pages' => 500,          // safety stop for runaway pagination
        'page_delay_ms' => 250,      // pause between pages (stay well under the rate limit)
        'cache_ttl' => 21600,        // seconds (6 h): eSIM Go asks clients not to poll the catalogue
    ],
];
