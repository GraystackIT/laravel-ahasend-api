<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ahasend API Key
    |--------------------------------------------------------------------------
    |
    | Your Ahasend API key. Generate one in your Ahasend dashboard under
    | Settings > API Keys. Required for all API requests.
    |
    */
    'api_key' => env('AHASEND_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Ahasend API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Ahasend API. Override only if you need to point
    | to a staging/sandbox environment.
    |
    */
    'base_url' => env('AHASEND_BASE_URL', 'https://api.ahasend.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Default From Address
    |--------------------------------------------------------------------------
    |
    | The default sender address used when no explicit from address is given.
    | Override per-send via the AhasendService or Mailable trait.
    |
    */
    'from' => [
        'address' => env('AHASEND_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'name'    => env('AHASEND_FROM_NAME', env('MAIL_FROM_NAME')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for inbound webhook event handling.
    |
    | - path:   The URI path for the webhook endpoint.
    | - secret: Optional shared secret used to verify Ahasend webhook
    |           signatures. Set AHASEND_WEBHOOK_SECRET in your .env file.
    |           Leave null to skip signature verification.
    |
    */
    'webhook' => [
        'path'   => env('AHASEND_WEBHOOK_PATH', 'ahasend/webhook'),
        'secret' => env('AHASEND_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage / Logging
    |--------------------------------------------------------------------------
    |
    | Control whether outgoing email and inbound webhook events are persisted.
    |
    | - store_logs:      true | false
    | - storage_driver:  "log" writes to Laravel's log channel.
    |                    "database" writes to the ahasend_messages table.
    |
    */
    'store_logs'     => env('AHASEND_STORE_LOGS', false),
    'storage_driver' => env('AHASEND_STORAGE_DRIVER', 'log'), // "log" or "database"

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Number of times a failed API request should be retried, and the delay
    | in milliseconds between each attempt.
    |
    */
    'retry' => [
        'times' => (int) env('AHASEND_RETRY_TIMES', 3),
        'delay' => (int) env('AHASEND_RETRY_DELAY_MS', 500),
    ],

];
