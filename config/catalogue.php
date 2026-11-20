<?php

/*
|--------------------------------------------------------------------------
| Plans & catalogue (Phase 5)
|--------------------------------------------------------------------------
*/

return [

    /*
    | Customer-facing currency for all catalogue prices (decision D3).
    | No currency conversion is applied anywhere (pending decision J5).
    */
    'currency' => 'USD',

    /*
    | Currencies a provider (eSIM Go) cost can be recorded in — the admin
    | dropdown. Add ISO 4217 codes here to offer more. A bundle that already
    | uses another code keeps it when edited.
    */
    'provider_currencies' => ['USD', 'EUR', 'GBP'],

    /*
    | Live catalogue switch (decision D5). OFF by default: the website keeps
    | its existing static design until real countries, plans and approved
    | prices have been entered in the admin panel.
    |
    | To switch on: add CATALOGUE_LIVE=true to .env, then run
    | `php artisan config:clear`. The public catalogue API is not affected
    | by this switch (it always lists only sellable plans).
    */
    'live' => (bool) env('CATALOGUE_LIVE', false),

    /*
    | How many destinations the home page shows (matches the existing design).
    */
    'home_destinations' => 8,
    'home_offers' => 12,

    /*
    | Existing website images per country (decision D8: reuse existing
    | assets, no new image columns). Keyed by ISO 3166-1 alpha-2 code.
    | A country's stored `flag_image` (older records only — the admin form no
    | longer sets it) overrides "flag"; otherwise the flag comes from this map.
    */
    'country_images' => [
        'AE' => ['flag' => 'assets/images/flag-round-uae.png', 'sphere' => 'assets/images/sphere-uae.webp', 'card' => 'assets/images/card-uae.webp'],
        'AU' => ['flag' => 'assets/images/flag-round-australia.png', 'card' => 'assets/images/card-australia.webp'],
        'BR' => ['flag' => 'assets/images/flag-round-brazil.png', 'card' => 'assets/images/card-brazil.webp'],
        'CN' => ['flag' => 'assets/images/flag-round-china.png', 'sphere' => 'assets/images/sphere-china.webp', 'card' => 'assets/images/card-china.webp'],
        'EG' => ['flag' => 'assets/images/flag-round-egypt.svg', 'sphere' => 'assets/images/sphere-egypt.webp', 'card' => 'assets/images/card-egypt.webp'],
        'FR' => ['flag' => 'assets/images/flag-round-france.png', 'card' => 'assets/images/card-france.webp'],
        'GB' => ['flag' => 'assets/images/flag-uk-round.webp', 'sphere' => 'assets/images/sphere-uk.webp'],
        'JP' => ['flag' => 'assets/images/flag-round-japan.png', 'sphere' => 'assets/images/sphere-japan.webp', 'card' => 'assets/images/card-japan.webp', 'banner' => 'assets/images/japan-esim-banner.png'],
        'NO' => ['flag' => 'assets/images/flag-round-norway.png', 'card' => 'assets/images/card-norway.webp'],
        'NZ' => ['flag' => 'assets/images/flag-round-newzealand.png', 'card' => 'assets/images/card-newzealand.webp'],
        'QA' => ['sphere' => 'assets/images/sphere-qatar.webp'],
        'TH' => ['flag' => 'assets/images/flag-round-thailand.png', 'sphere' => 'assets/images/sphere-thailand.webp', 'card' => 'assets/images/card-thailand.webp'],
        'US' => ['flag' => 'assets/images/flag-round-usa.png', 'sphere' => 'assets/images/sphere-usa.webp', 'card' => 'assets/images/card-usa.webp'],
    ],

    /*
    | Neutral existing images used when a country has no image of its own.
    */
    'fallback_images' => [
        'card' => 'assets/images/new-esim-banner.webp',
        'banner' => 'assets/images/new-esim-banner.webp',
    ],
];
