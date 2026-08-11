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
    | Ahasend Account ID
    |--------------------------------------------------------------------------
    |
    | Your Ahasend account UUID. Find it in your Ahasend dashboard. Required
    | for all API requests — every endpoint is scoped to an account.
    |
    */
    'account_id' => env('AHASEND_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Ahasend API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Ahasend API. Override only if you need to point
    | to a staging/sandbox environment.
    |
    */
    'base_url' => env('AHASEND_BASE_URL', 'https://api.ahasend.com/v2'),

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
    | Domain Management
    |--------------------------------------------------------------------------
    |
    | Ahasend has no "domain verified" webhook — only `domain.dns_error` — so a
    | pending domain is moved forward by an explicit DNS check, either on demand
    | or by the `ahasend:domains:poll` command.
    |
    | - max_unverified_per_owner: how many unverified domains one owner may hold
    |       at a time. Anything other than `verified` counts. Keeps an account
    |       from filling up with abandoned domains. Owner-less domains (our own
    |       shared ones) are never affected.
    |       NOTE: on Postgres a partial unique index enforces a ceiling of one
    |       regardless of this value. Raising it above 1 therefore also requires
    |       dropping `ahasend_domains_owner_unverified_unique`.
    | - pending_expiry_days: after how many days a still-unverified domain is
    |       deleted locally and remotely by `ahasend:domains:expire`, so a typo'd
    |       domain cannot lock an owner out forever.
    | - poll_max_age_days: pending domains older than this are no longer polled
    |       automatically; they wait for a manual check or for expiry.
    |
    */
    'domains' => [
        'max_unverified_per_owner' => (int) env('AHASEND_MAX_UNVERIFIED_DOMAINS', 1),
        'pending_expiry_days'      => (int) env('AHASEND_DOMAIN_PENDING_EXPIRY_DAYS', 14),
        'poll_max_age_days'        => (int) env('AHASEND_DOMAIN_POLL_MAX_AGE_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound Message Routing
    |--------------------------------------------------------------------------
    |
    | Inbound routes are account-level objects matched by a `recipient` pattern,
    | and Ahasend derives the owning domain from that pattern — so every domain
    | that should receive mail needs its own route. Each route carries its own
    | signing secret, which is why the endpoint takes the route id in the path.
    |
    | - path:     URI prefix of the inbound endpoint; the route id is appended.
    | - base_url: public base URL registered with Ahasend. Defaults to APP_URL.
    | - defaults: options every provisioned route is created with.
    |
    */
    'inbound' => [
        'path'     => env('AHASEND_INBOUND_PATH', 'ahasend/inbound'),
        'base_url' => env('AHASEND_INBOUND_BASE_URL', env('APP_URL')),
        'defaults' => [
            'attachments'         => (bool) env('AHASEND_INBOUND_ATTACHMENTS', true),
            'headers'             => (bool) env('AHASEND_INBOUND_HEADERS', true),
            'strip_replies'       => (bool) env('AHASEND_INBOUND_STRIP_REPLIES', true),
            'group_by_message_id' => (bool) env('AHASEND_INBOUND_GROUP_BY_MESSAGE_ID', true),
        ],
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
