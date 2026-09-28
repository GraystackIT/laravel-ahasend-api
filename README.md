# laravel-ahasend-api

A production-ready Laravel package for the [Ahasend](https://ahasend.com) transactional email API, powered by **Saloon v4**.

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13
- Saloon 4.x
- Symfony Mailer 6.4 / 7.x (bundled with Laravel)

## Installation

```bash
composer require graystackit/laravel-ahasend-api
```

The service provider is auto-discovered via Laravel's package discovery.

### Publish config

```bash
php artisan vendor:publish --tag=ahasend-config
```

### Publish & run migrations (optional — only needed for database storage driver)

```bash
php artisan vendor:publish --tag=ahasend-migrations
php artisan migrate
```

## Configuration

Set the following variables in your `.env` file:

```dotenv
AHASEND_API_KEY=your-api-key
AHASEND_ACCOUNT_ID=your-account-id
AHASEND_FROM_ADDRESS=hello@yourdomain.com
AHASEND_FROM_NAME="Your App"

# Optional
AHASEND_BASE_URL=https://api.ahasend.com/v2
AHASEND_WEBHOOK_SECRET=your-webhook-secret
AHASEND_STORE_LOGS=true
AHASEND_STORAGE_DRIVER=database   # "log" or "database"
AHASEND_RETRY_TIMES=3
AHASEND_RETRY_DELAY_MS=500
```

> **Note:** `AHASEND_ACCOUNT_ID` is required. You can find your account ID in the Ahasend dashboard.

## Usage

### Dependency injection

```php
use GraystackIT\Ahasend\AhasendService;

class OrderController
{
    public function __construct(private readonly AhasendService $mailer) {}

    public function confirm(): void
    {
        $this->mailer->sendHtml(
            to:          [['email' => 'customer@example.com', 'name' => 'Jane']],
            subject:     'Order confirmed',
            htmlContent: '<p>Your order is confirmed!</p>',
            textContent: 'Your order is confirmed!',
        );
    }
}
```

### Plain-text email

```php
$mailer->sendText(
    to:          [['email' => 'user@example.com']],
    subject:     'Hello',
    textContent: 'Hello from Ahasend!',
);
```

### HTML email

```php
$mailer->sendHtml(
    to:          [['email' => 'user@example.com']],
    subject:     'Hello',
    htmlContent: '<h1>Hello!</h1>',
    textContent: 'Hello!',   // optional plain-text fallback
);
```

### Email with attachments

```php
$mailer->sendWithAttachments(
    to:          [['email' => 'user@example.com']],
    subject:     'Your invoice',
    attachments: [
        ['path' => storage_path('invoices/inv-001.pdf')],           // file path
        ['name' => 'data.csv', 'content' => $csvBase64, 'mime_type' => 'text/csv'], // raw
    ],
    htmlContent: '<p>Please find your invoice attached.</p>',
);
```

Each attachment also accepts an optional `content_id` and `content_disposition`. Set `content_id` to reference the attachment inline from `htmlContent` (e.g. `<img src="cid:image1@example.com">`):

```php
$mailer->sendWithAttachments(
    to:          [['email' => 'user@example.com']],
    subject:     'Inline image',
    attachments: [
        [
            'name' => 'logo.png',
            'content' => $pngBase64,
            'mime_type' => 'image/png',
            'content_id' => '<image1@example.com>',
            'content_disposition' => 'inline',
        ],
    ],
    htmlContent: '<p><img src="cid:image1@example.com"></p>',
);
```

### Templated email

Send using a saved Ahasend transactional template, which supplies the subject, preview text and both bodies. A `templateId` cannot be combined with `htmlContent`/`textContent`/`ampContent` (the template supplies them) or with `cc`/`bcc` (only the plain send endpoint supports templates) — `AhasendService` throws `AhasendException` client-side if you try:

```php
$mailer->sendTemplate(
    to:            [['email' => 'user@example.com']],
    templateId:    'tmpl-uuid-1',
    substitutions: ['name' => 'Alice', 'order_id' => '1234'],
);
```

### CC / BCC

Pass `cc` and `bcc` arrays to any convenience method. When CC or BCC recipients are present the package automatically routes the request to the Ahasend conversational endpoint (`POST /messages/conversation`), which is the only endpoint that supports those fields:

```php
$mailer->sendHtml(
    to:          [['email' => 'a@example.com']],
    subject:     'Test',
    htmlContent: '<p>Hi</p>',
    cc:          [['email' => 'b@example.com']],
    bcc:         [['email' => 'c@example.com']],
);
```

### Low-level `EmailMessage`

```php
use GraystackIT\Ahasend\Data\EmailMessage;

$message = new EmailMessage(
    fromEmail:   'from@example.com',
    fromName:    'Sender',
    to:          [['email' => 'to@example.com']],
    subject:     'Custom',
    htmlContent: '<p>Hello</p>',
);

$ahasendMessageId = $mailer->send($message);
```

`EmailMessage` also accepts these optional fields, all passed straight through to the Ahasend API: `tags` (string[]), `tracking` (`['open' => bool, 'click' => bool]`), `schedule` (`['first_attempt' => ..., 'expires' => ...]`, RFC3339), `retention` (`['metadata' => int, 'data' => int]`, days), `substitutions` (template variables — not supported on the conversational/CC-BCC endpoint), `sandboxResult` (`deliver`/`bounce`/`defer`/`fail`/`suppress`), `sandbox` (bool — route through AhaSend's sandbox without sending real mail), `replyTo` (`['email' => ..., 'name' => ...]`), `headers` (custom header map), `ampContent` (AMP4EMAIL body), and `templateId` (see [Templated email](#templated-email) — cannot combine with body content or cc/bcc).

## Webhook handling

Register your endpoint URL in the Ahasend dashboard:

```
https://yourdomain.com/ahasend/webhook
```

The path is configurable via `AHASEND_WEBHOOK_PATH`. Incoming events fire Laravel events you can listen to:

| Ahasend event | Laravel event | Extra constructor argument(s) |
|---|---|---|
| `message.reception` | `MailReceived` | — (`message.routing` is handled separately — see [Inbound mail](#inbound-mail)) |
| `message.delivered` | `MailDelivered` | `$deliveryAttempt` (nullable array — smtp_code, response, etc., when Ahasend recorded one) |
| `message.opened` | `MailOpened` | — |
| `message.clicked` | `MailClicked` | `$url` |
| `message.failed` | `MailFailed` | `$reason` — **always null**: this event reports retry exhaustion, and Ahasend's payload carries no per-attempt diagnostic for it |
| `message.bounced` | `MailBounced` | `$bounceType` — absent from every payload observed so far; see `MailBounced::outboundMessageId()` and listen for `SuppressionCreated` to learn why a message bounced |
| `message.suppressed` | `MailSuppressed` | `$suppressionType` — **always null**: Ahasend's payload carries no suppression-reason field on this event; see the matching `SuppressionCreated` event or the Suppressions API's `reason` instead |
| `message.transient_error` | `MailTransientError` | `$reason` (from `delivery_attempt.description`/`.response`, when present), `$deliveryAttempt` |
| `domain.dns_error` | `DomainDnsError` | — |
| `suppression.created` | `SuppressionCreated` | `$type` (from the payload's `reason` field) |

Every event also carries the raw `$payload` (the webhook's `data` object) so you can read any field Ahasend adds that isn't yet a first-class constructor argument.

### Listening to events

```php
// In EventServiceProvider or a listener class:
Event::listen(MailDelivered::class, function (MailDelivered $event): void {
    // $event->messageId, $event->recipient, $event->deliveryAttempt, $event->payload
});
```

## Laravel Mail driver

The package registers a native Laravel mail transport driver so you can send any standard Laravel `Mailable` through AhaSend without touching your existing Mailable code.

### 1. Configure the mailer

Add an `ahasend` entry to the `mailers` array in `config/mail.php`:

```php
// config/mail.php
'mailers' => [
    // ... other mailers ...

    'ahasend' => [
        'transport' => 'ahasend',
    ],
],
```

The transport reads API credentials and sender defaults from the `ahasend` config (i.e. the same `AHASEND_*` variables you already set).

To make AhaSend the **default** mailer, update your `.env`:

```dotenv
MAIL_MAILER=ahasend
```

### 2. Send a Mailable

```php
use App\Mail\OrderShipped;
use Illuminate\Support\Facades\Mail;

// Uses the default mailer if MAIL_MAILER=ahasend
Mail::to('customer@example.com')->send(new OrderShipped($order));

// Or target the driver explicitly
Mail::mailer('ahasend')
    ->to('customer@example.com')
    ->cc('manager@example.com')
    ->send(new OrderShipped($order));
```

### 3. Example Mailable

```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderShipped extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your order has shipped');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.order-shipped',   // resources/views/emails/order-shipped.blade.php
            text: 'emails.order-shipped-text',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path("invoices/{$this->order->id}.pdf"))
                ->as('invoice.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
```

### 4. Required `.env` variables

```dotenv
AHASEND_API_KEY=your-api-key
AHASEND_ACCOUNT_ID=your-account-id
AHASEND_FROM_ADDRESS=hello@yourdomain.com
AHASEND_FROM_NAME="Your App"

# Make AhaSend the default mailer
MAIL_MAILER=ahasend
```

### Supported features

| Feature | Supported |
|---|---|
| HTML body | Yes |
| Plain-text body | Yes |
| Multiple `To` recipients | Yes |
| CC | Yes |
| BCC | Yes |
| File attachments | Yes (auto base64 encoded) |
| From address / name | Yes (from Mailable or config fallback) |

### Transport internals

The driver is implemented as `GraystackIT\Ahasend\Mail\AhaSendTransport`, which extends Symfony's `AbstractTransport`. It converts the Symfony `Email` object into the `EmailMessage` DTO used by `AhasendService::send()`, preserving all recipients, headers, and attachments. Errors thrown by `AhasendService` are re-wrapped as Symfony `TransportException` so Laravel's mail system handles them consistently.

---

## Mailable tracking

Use the `TracksAhasendMail` trait in any Mailable to attach a UUID `X-Ahasend-Message-Id` header and (optionally) store the outgoing record in the database:

```php
use GraystackIT\Ahasend\Traits\TracksAhasendMail;
use Illuminate\Mail\Mailable;

class OrderShipped extends Mailable
{
    use TracksAhasendMail;

    public function build(): self
    {
        $this->initAhasendTracking(recipient: $this->order->email);

        return $this->subject('Your order has shipped')
                    ->view('emails.order-shipped');
    }
}
```

---

## Messages

Retrieve and manage sent or scheduled messages via `MessageService`.

```php
use GraystackIT\Ahasend\Services\MessageService;
use GraystackIT\Ahasend\Enums\MessageStatus;

class MyController
{
    public function __construct(private readonly MessageService $messages) {}
}
```

### Get a single message

```php
$message = $messages->get('msg-abc123');

echo $message->id;            // 'msg-abc123'
echo $message->subject;       // 'Hello World'
echo $message->sender;        // 'sender@example.com'
echo $message->recipient;     // 'recipient@example.com'
echo $message->status->value; // 'delivered'
echo $message->status->isTerminal(); // true
echo $message->direction;             // 'outbound'
echo $message->numAttempts;           // 1
echo $message->clickCount;            // 0
echo $message->openCount;             // 1
echo $message->bounceClassification;  // null unless is_bounce_notification
```

Ahasend documents `status` as an open set of values — new statuses (e.g. sandbox-prefixed ones) can appear without notice. Parsing never throws: an unrecognized value maps to `MessageStatus::Unknown`, and `$message->rawStatus` always holds the exact string Ahasend returned so you don't lose information when that happens.

### List messages

Uses cursor-based pagination, plus optional filters:

```php
$result = $messages->list(
    limit:     25,                       // optional — max results to return
    after:     'cursor-xyz',             // optional — cursor for the next page
    before:    'cursor-abc',             // optional — cursor for the previous page
    status:    'delivered',              // optional
    sender:    'sender@example.com',     // optional
    recipient: 'recipient@example.com',  // optional
    tags:      'welcome',                // optional — comma-separated
    fromTime:  '2026-01-01T00:00:00Z',   // optional — RFC3339
    toTime:    '2026-01-31T23:59:59Z',   // optional — RFC3339
);

foreach ($result['data'] as $message) {
    echo $message->id . ': ' . $message->subject;
}

// $result['pagination'] contains has_more / next_cursor / previous_cursor
```

### Cancel a scheduled message

```php
$cancelled = $messages->cancel('msg-scheduled-001'); // true on success
```

---

## SMTP Credentials

Manage programmatic SMTP credentials via `SmtpCredentialService`.

```php
use GraystackIT\Ahasend\Services\SmtpCredentialService;

class MyController
{
    public function __construct(private readonly SmtpCredentialService $smtp) {}
}
```

### Create an SMTP credential

```php
// Global credential (can send from any domain)
$credential = $smtp->create('My Application');

// Scoped credential (restricted to specific domains)
$credential = $smtp->create(
    name:    'My Application',
    scope:   'scoped',
    domains: ['yourdomain.com', 'anotherdomain.com'],
);

// Sandbox credential (no real emails sent)
$credential = $smtp->create('Test App', sandbox: true);

// Save the password — the API will not return it again.
echo $credential->id;       // 'cred-xyz'
echo $credential->username; // 'smtp_my_application'
echo $credential->password; // 'generated-secret' (only available on create)
echo $credential->sandbox;  // bool
echo $credential->scope;    // 'global' or 'scoped'
echo $credential->domains;  // string[] — the domains it's scoped to; empty when scope is 'global'
```

The SMTP host/port to connect to (`send.ahasend.com` / `send-us.ahasend.com`, ports 25/587/2525) are fixed values, not returned per credential, so they aren't modeled here.

### List all SMTP credentials

Uses cursor-based pagination:

```php
$credentials = $smtp->list(
    limit:  10,           // optional
    after:  'cursor-xyz', // optional
    before: 'cursor-abc', // optional
);

foreach ($credentials as $cred) {
    echo $cred->id . ': ' . $cred->name;
}
```

### Get a single SMTP credential

```php
$credential = $smtp->get('cred-xyz');
```

### Delete an SMTP credential

```php
$smtp->delete('cred-xyz'); // true on success
```

---

## Suppressions

Manage the suppression list via `SuppressionService`.

```php
use GraystackIT\Ahasend\Services\SuppressionService;

class MyController
{
    public function __construct(private readonly SuppressionService $suppressions) {}
}
```

### Add a suppression

```php
$suppression = $suppressions->create(
    email:     'user@example.com',
    expiresAt: '2026-12-31T00:00:00Z', // RFC3339 datetime — required
    reason:    'User unknown',          // optional
    domain:    'example.com',           // optional — restrict to a sending domain
);

echo $suppression->id;     // 'sup-xyz'
echo $suppression->email;  // 'user@example.com'
```

`$suppression->protected` is `true` when the recipient made the suppression decision themselves (unsubscribed or reported spam) — `deleteAll()` keeps protected suppressions even though it removes everything else.

### List suppressions

Uses cursor-based pagination, plus optional filters:

```php
$result = $suppressions->list(
    limit:    50,                    // optional
    after:    'cursor-xyz',          // optional
    before:   'cursor-abc',          // optional
    domain:   'example.com',         // optional — filter by sending domain
    email:    'user@example.com',    // optional — filter by recipient email
    fromTime: '2026-01-01T00:00:00Z', // optional — RFC3339, created after
    toTime:   '2026-01-31T23:59:59Z', // optional — RFC3339, created before
);

foreach ($result['data'] as $suppression) {
    echo $suppression->email;
}

// $result['pagination'] contains has_more / next_cursor / previous_cursor
```

### Delete a specific suppression

Deletes by email (optionally scoped to a sending domain):

```php
$suppressions->delete('user@example.com'); // true on success
$suppressions->delete('user@example.com', domain: 'example.com'); // scoped to one domain
```

### Delete all suppressions

Optionally scoped to a single sending domain:

```php
$suppressions->deleteAll(); // true on success — wipes the entire account list
$suppressions->deleteAll(domain: 'example.com'); // wipes only suppressions for this domain
```

---

## Reports

Retrieve analytics data via `ReportService`.

```php
use GraystackIT\Ahasend\Services\ReportService;

class MyController
{
    public function __construct(private readonly ReportService $reports) {}
}
```

All date/time parameters use RFC3339 format (e.g. `2024-01-01T00:00:00Z`). Every report method returns a **list of time-bucketed entries** (one per `group_by` interval — `hour`, `day`, `week`, or `month`; defaults to `day`), matching AhaSend's statistics response shape. Note: AhaSend rate-limits these three statistics endpoints to 1 request/second with no burst allowance, versus 100 req/sec elsewhere — avoid tight polling loops.

### Bounce statistics

```php
$buckets = $reports->bounceStatistics(
    fromTime:         '2024-01-01T00:00:00Z', // optional
    toTime:           '2024-01-31T23:59:59Z', // optional
    senderDomain:     'gmail.com',             // optional — filter by sending domain
    recipientDomains: 'gmail.com,outlook.com', // optional — comma-separated
    tags:             'transactional',         // optional — comma-separated
    groupBy:          'day',                   // optional — hour, day, week, month
);

foreach ($buckets as $bucket) {
    echo $bucket->fromTimestamp . ' – ' . $bucket->toTimestamp . "\n";

    foreach ($bucket->bounces as $bounce) {
        echo "  {$bounce['classification']}: {$bounce['count']}\n";
    }
}
```

### Deliverability breakdown

```php
$buckets = $reports->deliverabilityBreakdown(
    fromTime:         '2024-01-01T00:00:00Z', // optional
    toTime:           '2024-01-31T23:59:59Z', // optional
    senderDomain:     'yourdomain.com',        // optional
    recipientDomains: 'gmail.com,outlook.com', // optional — comma-separated
    tags:             'transactional,welcome', // optional — comma-separated
    groupBy:          'day',                   // optional — hour, day, week, month
);

foreach ($buckets as $bucket) {
    echo $bucket->fromTimestamp . ': ' . $bucket->deliveredCount . ' delivered, ' . $bucket->bouncedCount . ' bounced';
}
```

### Delivery time analytics

```php
$buckets = $reports->deliveryTimeAnalytics(
    fromTime:         '2024-01-01T00:00:00Z', // optional
    toTime:           '2024-01-31T23:59:59Z', // optional
    senderDomain:     'yahoo.com',             // optional
    recipientDomains: 'yahoo.com',             // optional — comma-separated
    tags:             'transactional',         // optional — comma-separated
    groupBy:          'day',                   // optional — hour, day, week, month
);

foreach ($buckets as $bucket) {
    echo $bucket->fromTimestamp . ': avg ' . $bucket->avgDeliveryTime . 's across ' . $bucket->deliveredCount . ' messages';

    foreach ($bucket->deliveryTimes as $byDomain) {
        echo "  {$byDomain['recipient_domain']}: {$byDomain['delivery_time']}s ({$byDomain['count']} messages)\n";
    }
}
```

---

## Error handling

All service methods throw `AhasendException` on API errors. The exception wraps the Saloon `RequestException` and exposes the HTTP status code.

```php
use GraystackIT\Ahasend\Exceptions\AhasendException;

try {
    $messages->get('nonexistent-id');
} catch (AhasendException $e) {
    echo $e->getCode();    // 404
    echo $e->getMessage(); // "Ahasend API error [404]: ..."
}
```

---

## Domains

Domain management is stateful: the package keeps an `ahasend_domains` row alongside the
remote object so an application can show verification progress without polling the API on
every page load. Run the migrations before using it.

```php
use GraystackIT\Ahasend\Services\DomainManager;

$domains = app(DomainManager::class);

// $owner is any Eloquent model — the package never interprets it.
$domain = $domains->add('acme.at', $owner);

foreach ($domain->outstandingRecords() as $record) {
    echo "{$record->type->value}  {$record->host}  {$record->content}";
}
```

### Verification is poll-driven, not webhook-driven

AhaSend emits **no event when a domain becomes valid** — the only domain event is
`domain.dns_error`. A domain therefore only ever moves forward through an explicit check:

```php
$domain = $domains->refresh($domain);   // "check DNS" button

$domain->isVerified();                  // true once every required record propagated
```

Schedule the poll so pending domains progress without anyone pressing a button, and the
expiry so an abandoned domain does not occupy its owner's slot forever:

```php
Schedule::command('ahasend:domains:poll')->hourly();
Schedule::command('ahasend:domains:expire')->daily();
```

### One open domain per owner

`DomainManager::add()` refuses a new domain while the owner still holds an unverified one,
so an account cannot fill up with abandoned domains. On Postgres a partial unique index
enforces the same rule at the database level, which is what settles two simultaneous adds.
Owner-less domains — an application's own shared domains — are exempt.

```php
use GraystackIT\Ahasend\Exceptions\DomainLimitExceededException;

try {
    $domains->add('second.at', $owner);
} catch (DomainLimitExceededException $e) {
    $e->blockingDomains;   // ['first.at'] — offer "check DNS" or "delete" for these
}
```

Raise the ceiling with `AHASEND_MAX_UNVERIFIED_DOMAINS`; going above 1 also means dropping
the `ahasend_domains_owner_unverified_unique` index.

---

## Inbound mail

A route's `recipient` pattern is domain-bound — `*` means every address on *that* domain —
so each receiving domain needs its own route, and AhaSend generates a separate signing
secret for each. There is no account-wide catch-all across domains, and the secret cannot be
supplied when creating a route.

That leaves two ways to hold those secrets, and the package serves both:

| Endpoint | For | Secret |
| --- | --- | --- |
| `POST /ahasend/webhook` | delivery, bounces, suppressions, `domain.dns_error` | `AHASEND_WEBHOOK_SECRET` |
| `POST /ahasend/inboundmail` | routes you create in the dashboard | `AHASEND_INBOUND_SECRET` |
| `POST /ahasend/inbound/{route}` | routes the package provisions per customer domain | stored with the route |

**Use the dashboard for your own domains.** They are few and known up front, so create the
route by hand, point it at `/ahasend/inboundmail` and put its secret in the environment —
one secret per domain:

```dotenv
AHASEND_INBOUND_SECRETS="postbox.example.com:whsec_postbox,tickets.example.com:whsec_tickets"
```

Publishing the config lets you write the same map as an array instead, which is the
canonical form:

```php
'secrets' => [
    'postbox.example.com' => env('AHASEND_INBOUND_SECRET_POSTBOX'),
    'tickets.example.com' => env('AHASEND_INBOUND_SECRET_TICKETS'),
],
```

**The secret that verifies a payload is what tells you which domain it was delivered to**,
and `InboundMailReceived::$deliveredForDomain` carries it. Branch on that, never on the
address list: a mail addressed to two of your domains matches two routes and is delivered
**twice**, and both payloads carry the same addresses. Only the signature separates them —
branch on addresses and both consumers process both deliveries.

**The stored-secret path is for customer domains**, where `RouteManager` provisions a route
the moment a domain verifies. There is no chance to put those secrets in a config file, so
the route id in the URL is what the stored secret is looked up by. Nobody ever handles them.

Two things this endpoint refuses rather than waving through: a payload when no secret is
configured, and a provisioned route whose stored secret is empty — both answer `503`,
because on a public endpoint "no secret" must never mean "no verification". Signed
timestamps are checked against a tolerance window (`AHASEND_WEBHOOK_TOLERANCE_SECONDS`,
default 300) so a captured request cannot be replayed forever, and all three endpoints carry
a rate limit (`AHASEND_INBOUND_THROTTLE`, default `120,1`).

```php
use GraystackIT\Ahasend\Events\InboundMailReceived;

class RouteInboundMail
{
    public function handle(InboundMailReceived $event): void
    {
        // Queue the real work: AhaSend retries on any non-2xx response, so slow
        // synchronous handling produces duplicates. $deliveryId is stable across
        // those retries — use it as the idempotency key.
        ProcessInboundMail::dispatch($event->message, $event->deliveryId);
    }
}
```

`InboundMessage` normalises the payload:

```php
$message->text();               // reply-stripped body when the route produced one
$message->parentMessageIds();   // In-Reply-To, then References newest-first
$message->isAutomated();        // Auto-Submitted / Precedence / List-Id / X-Auto-Response-Suppress
$message->inlineAttachments();  // parts referenced by cid: in the HTML body
$message->attachments[0]->contents();   // decoded bytes
$message->spamScore;
$message->raw;                  // anything not modelled yet
```

Set `AHASEND_INBOUND_BASE_URL` to the publicly reachable URL of the application — AhaSend
has to be able to POST to it, and route provisioning fails loudly if it is missing.

### Routes created in the dashboard

Prefer letting the package provision routes: it generates the URL, stores the secret and
needs nothing pasted. For a route that already exists — created by hand, or whose local
record was lost — adopt it once:

```bash
php artisan ahasend:routes:import rt_abc123 --secret=whsec_from_the_dashboard
```

The secret has to come from the dashboard because Ahasend returns it only at creation. The
command stores it and repoints the route at this application's inbound endpoint, since that
URL carries the id the secret is looked up by. `--keep-url` skips the repointing, in which
case inbound mail keeps going wherever the route pointed before.

---

## Testing

```bash
composer test
```

## License

MIT
