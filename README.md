# laravel-ahasend-api

A production-ready Laravel package for the [Ahasend](https://ahasend.com) transactional email API, powered by **Saloon v4**.

## Requirements

- PHP 8.2+
- Laravel 10 / 11 / 12
- Saloon 4.x

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
AHASEND_FROM_ADDRESS=hello@yourdomain.com
AHASEND_FROM_NAME="Your App"

# Optional
AHASEND_BASE_URL=https://api.ahasend.com/v1
AHASEND_WEBHOOK_SECRET=your-webhook-secret
AHASEND_STORE_LOGS=true
AHASEND_STORAGE_DRIVER=database   # "log" or "database"
AHASEND_RETRY_TIMES=3
AHASEND_RETRY_DELAY_MS=500
```

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

### CC / BCC

Pass `cc` and `bcc` arrays to any convenience method:

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

## Webhook handling

Register your endpoint URL in the Ahasend dashboard:

```
https://yourdomain.com/ahasend/webhook
```

The path is configurable via `AHASEND_WEBHOOK_PATH`. Incoming events fire Laravel events you can listen to:

| Ahasend event | Laravel event |
|---|---|
| `delivered` | `MailDelivered` |
| `opened` | `MailOpened` |
| `failed` | `MailFailed` |
| `bounced` | `MailBounced` |

### Listening to events

```php
// In EventServiceProvider or a listener class:
Event::listen(MailDelivered::class, function (MailDelivered $event): void {
    // $event->messageId, $event->recipient, $event->payload
});
```

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

## Testing

```bash
composer test
```

## License

MIT
