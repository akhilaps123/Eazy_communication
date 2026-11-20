<?php

/*
|--------------------------------------------------------------------------
| Razorpay (Phase 8) — TEST MODE ONLY
|--------------------------------------------------------------------------
| All values come from .env (config:cache safe). The key secret and the
| webhook secret are only ever used on the server: never in HTML, JS or logs.
|
| Online payment is offered only when: mode is "test", a key id + key secret
| are set, the key id is not a live key, and RAZORPAY_CURRENCY is set and
| equals the order's currency. There is no currency conversion: if the order
| currency (USD) differs, payment is refused rather than charging a different
| amount. To accept USD the Razorpay account needs International Payments
| activated (Razorpay Dashboard → Account & Settings).
*/

return [

    'mode' => env('RAZORPAY_MODE', 'test'),

    'key_id' => env('RAZORPAY_KEY_ID'),
    'key_secret' => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

    // The currency the Razorpay account accepts for these orders (e.g. USD).
    'currency' => env('RAZORPAY_CURRENCY'),

    // Number of decimals per currency (Razorpay amounts are in the smallest unit).
    'currency_exponents' => [
        'USD' => 2,
        'INR' => 2,
    ],

    'api_url' => 'https://api.razorpay.com/v1',
    'checkout_script' => 'https://checkout.razorpay.com/v1/checkout.js',
    'timeout' => 15,

    // Shown in the Razorpay Checkout window.
    'merchant_name' => 'Eazy Communication',
];
