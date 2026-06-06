<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout Session TTL
    |--------------------------------------------------------------------------
    | How long (in seconds) a checkout session stays alive before it expires.
    | The STK Push itself times out in ~60 s, but we keep the session longer
    | so the user can retry or view the result.
    */
    'session_ttl' => env('MPESA_CHECKOUT_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Polling Interval & Timeout (used by the front-end JS)
    |--------------------------------------------------------------------------
    */
    'poll_interval_ms' => env('MPESA_CHECKOUT_POLL_INTERVAL', 3000),
    'poll_timeout_ms'  => env('MPESA_CHECKOUT_POLL_TIMEOUT', 120000),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix & Middleware
    |--------------------------------------------------------------------------
    */
    'route_prefix'     => env('MPESA_CHECKOUT_ROUTE_PREFIX', 'mpesa-checkout'),
    'middleware'       => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | The cache store used to persist CheckoutSession objects.
    | null means the default application cache.
    */
    'cache_store'      => env('MPESA_CHECKOUT_CACHE_STORE', null),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Optional HMAC secret to verify incoming M-Pesa confirmation callbacks.
    | Leave null to skip signature verification (not recommended in production).
    */
    'webhook_secret'   => env('MPESA_CHECKOUT_WEBHOOK_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | Currency & Locale
    |--------------------------------------------------------------------------
    */
    'currency'         => 'KES',
    'locale'           => 'en',

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    | Shown in the checkout modal header.
    */
    'brand_name'       => env('APP_NAME', 'My App'),
    'brand_logo'       => env('MPESA_CHECKOUT_LOGO', null),

    /*
    |--------------------------------------------------------------------------
    | Success / Cancel Redirect URLs (standalone page flow)
    |--------------------------------------------------------------------------
    */
    'redirect_success' => env('MPESA_CHECKOUT_SUCCESS_URL', '/'),
    'redirect_cancel'  => env('MPESA_CHECKOUT_CANCEL_URL', '/'),

];
