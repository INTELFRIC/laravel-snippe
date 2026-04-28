<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Snippe API Key
    |--------------------------------------------------------------------------
    | Your Snippe API key. You can find this in your Snippe dashboard.
    | Set SNIPPE_API_KEY in your .env file.
    |
    */
    'api_key' => env('SNIPPE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Snippe API Base URL
    |--------------------------------------------------------------------------
    | The base URL for the Snippe API.
    |
    */
    'base_url' => env('SNIPPE_BASE_URL', 'https://api.snippe.sh/v1'),

    /*
    |--------------------------------------------------------------------------
    | Default Webhook URL
    |--------------------------------------------------------------------------
    | Set a default webhook URL for all outgoing payment requests.
    | You can override this per payment using ->webhook('url').
    |
    */
    'webhook_url' => env('SNIPPE_WEBHOOK_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    | The URI path where the package exposes its webhook handler.
    | Full URL will be: https://yourapp.com/{webhook_path}
    |
    */
    'webhook_path' => env('SNIPPE_WEBHOOK_PATH', 'snippe/webhook'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Your Snippe webhook signing secret. Used to verify that incoming webhook
    | requests genuinely come from Snippe (HMAC-SHA256 signature check).
    | Find this in your Snippe Dashboard → Webhooks → Signing Secret.
    | Set SNIPPE_WEBHOOK_SECRET in your .env file.
    |
    */
    'webhook_secret' => env('SNIPPE_WEBHOOK_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    | The maximum number of seconds to wait for an API response.
    |
    */
    'timeout' => env('SNIPPE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    | Default currency for all payments.
    |
    */
    'currency' => env('SNIPPE_CURRENCY', 'TZS'),

];
