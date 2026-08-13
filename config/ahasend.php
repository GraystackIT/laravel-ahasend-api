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
    | A route's `recipient` pattern is domain-bound — `*` means every address on
    | that one domain — so each receiving domain needs its own route, and Ahasend
    | generates a separate signing secret for each. There are two ways to hold
    | those secrets, and both endpoints are always available:
    |
    | 1. Routes you manage in the dashboard (the application's own domains) point
    |    at `static_path` and are verified against `secrets` below — a map of
    |    domain to signing secret. The secret that verifies a payload is what
    |    authenticates the delivery domain, and consumers branch on that: a mail
    |    addressed to two of your domains matches two routes and is delivered
    |    twice, with the same address list in both payloads. Only the signature
    |    tells the two deliveries apart.
    | 2. Routes the application provisions per customer domain point at
    |    `path/{route}` — their secrets are generated at creation and stored with
    |    the route, since there is no chance to put them in a config file.
    |
    | - secrets:     domain => signing secret. Publishing this config lets you
    |                write the array directly; otherwise it is parsed from
    |                AHASEND_INBOUND_SECRETS as "domain:secret,domain:secret".
    | - static_path: endpoint for dashboard-managed routes.
    | - path:        endpoint prefix for provisioned routes; the id is appended.
    | - base_url:    public base URL registered with Ahasend. Defaults to APP_URL.
    | - tolerance:   how many seconds a signed timestamp may deviate from now,
    |                so a captured request cannot be replayed indefinitely.
    | - throttle:    rate limit for the public inbound and webhook endpoints,
    |                as "attempts,minutes". Empty disables it.
    | - defaults:    options every provisioned route is created with. Grouping by
    |                message id must stay off: one request per recipient is what
    |                makes Ahasend fan a multi-recipient mail out for us, each
    |                event carrying a single unambiguous `recipient`.
    |
    */
    'inbound' => [
        'secrets'     => GraystackIT\Ahasend\Support\InboundSecrets::parse(
            env('AHASEND_INBOUND_SECRETS'),
        ),
        // Directory to write every verified payload to, one JSON file per
        // delivery, for capturing real payloads as test fixtures. Runs only in
        // the local and testing environments: payloads carry personal data and
        // base64 attachment bytes, and nothing prunes them, so a path set on a
        // production system is ignored rather than obeyed.
        'capture_path' => env('AHASEND_INBOUND_CAPTURE_PATH'),
        'static_path' => env('AHASEND_INBOUND_STATIC_PATH', 'ahasend/inboundmail'),
        'path'        => env('AHASEND_INBOUND_PATH', 'ahasend/inbound'),
        'base_url'    => env('AHASEND_INBOUND_BASE_URL', env('APP_URL')),
        'tolerance'   => (int) env('AHASEND_WEBHOOK_TOLERANCE_SECONDS', 300),
        'throttle'    => env('AHASEND_INBOUND_THROTTLE', '120,1'),
        'defaults' => [
            'attachments'         => (bool) env('AHASEND_INBOUND_ATTACHMENTS', true),
            'headers'             => (bool) env('AHASEND_INBOUND_HEADERS', true),
            'strip_replies'       => (bool) env('AHASEND_INBOUND_STRIP_REPLIES', true),
            'group_by_message_id' => (bool) env('AHASEND_INBOUND_GROUP_BY_MESSAGE_ID', false),
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
