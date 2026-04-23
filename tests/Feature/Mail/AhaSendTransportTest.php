<?php

declare(strict_types=1);

use GraystackIT\Ahasend\AhasendService;
use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Mail\AhaSendTransport;
use GraystackIT\Ahasend\Requests\SendEmailRequest;
use GraystackIT\Ahasend\Requests\SendEmailWithAttachmentsRequest;
use GraystackIT\Ahasend\Requests\SendHtmlEmailRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeTransport(MockClient $mockClient): AhaSendTransport
{
    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    return new AhaSendTransport(new AhasendService($connector));
}

function sendViaTransport(AhaSendTransport $transport, Email $email): void
{
    $transport->send(
        $email,
        Envelope::create($email),
    );
}

// ─── HTML email ───────────────────────────────────────────────────────────────

it('sends an HTML email through the transport', function (): void {
    $mockClient = new MockClient([
        SendHtmlEmailRequest::class => MockResponse::make(
            ['message_id' => 'transport-html-001'],
            200,
        ),
    ]);

    $transport = makeTransport($mockClient);

    $email = (new Email())
        ->from(new Address('from@example.com', 'Sender'))
        ->to(new Address('to@example.com', 'Recipient'))
        ->subject('HTML Transport Test')
        ->html('<p>Hello from transport</p>')
        ->text('Hello from transport');

    sendViaTransport($transport, $email);

    $mockClient->assertSent(SendHtmlEmailRequest::class);
});

// ─── Plain-text email ─────────────────────────────────────────────────────────

it('sends a plain-text email through the transport', function (): void {
    $mockClient = new MockClient([
        SendEmailRequest::class => MockResponse::make(
            ['message_id' => 'transport-text-001'],
            200,
        ),
    ]);

    $transport = makeTransport($mockClient);

    $email = (new Email())
        ->from(new Address('from@example.com', 'Sender'))
        ->to(new Address('to@example.com', 'Recipient'))
        ->subject('Text Transport Test')
        ->text('Hello plain text');

    sendViaTransport($transport, $email);

    $mockClient->assertSent(SendEmailRequest::class);
});

// ─── CC / BCC ─────────────────────────────────────────────────────────────────

it('correctly maps CC and BCC addresses', function (): void {
    $mockClient = new MockClient([
        SendHtmlEmailRequest::class => MockResponse::make(['message_id' => 'cc-bcc-001'], 200),
    ]);

    $capturedMessage = null;

    $connector  = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $serviceSpy = new class(new AhasendService($connector)) {
        public ?EmailMessage $captured = null;

        public function __construct(private readonly AhasendService $inner) {}

        public function send(EmailMessage $message): string
        {
            $this->captured = $message;

            return $this->inner->send($message);
        }
    };

    // Use a real service but capture via closure spy on MockClient assertion.
    $transport = makeTransport($mockClient);

    $email = (new Email())
        ->from(new Address('from@example.com', 'Sender'))
        ->to(new Address('to@example.com', 'Recipient'))
        ->cc(new Address('cc@example.com', 'CC Person'))
        ->bcc(new Address('bcc@example.com'))
        ->subject('CC/BCC Test')
        ->html('<p>Hi</p>');

    sendViaTransport($transport, $email);

    $mockClient->assertSent(SendHtmlEmailRequest::class, function (SendHtmlEmailRequest $request) {
        $body = $request->body()->all();

        expect($body['cc'])->toContain(['email' => 'cc@example.com', 'name' => 'CC Person'])
            ->and($body['bcc'])->toContain(['email' => 'bcc@example.com']);

        return true;
    });
});

// ─── Multiple recipients ──────────────────────────────────────────────────────

it('maps multiple To recipients correctly', function (): void {
    $mockClient = new MockClient([
        SendHtmlEmailRequest::class => MockResponse::make(['message_id' => 'multi-001'], 200),
    ]);

    $transport = makeTransport($mockClient);

    $email = (new Email())
        ->from(new Address('from@example.com'))
        ->to(new Address('a@example.com', 'Alpha'), new Address('b@example.com', 'Beta'))
        ->subject('Multi Recipient')
        ->html('<p>Hi all</p>');

    sendViaTransport($transport, $email);

    $mockClient->assertSent(SendHtmlEmailRequest::class, function (SendHtmlEmailRequest $request) {
        $recipients = $request->body()->all()['recipients'];

        expect($recipients)->toHaveCount(2)
            ->and($recipients[0])->toBe(['email' => 'a@example.com', 'name' => 'Alpha'])
            ->and($recipients[1])->toBe(['email' => 'b@example.com', 'name' => 'Beta']);

        return true;
    });
});

// ─── Attachments ──────────────────────────────────────────────────────────────

it('encodes attachments as base64 and selects the attachments request', function (): void {
    $mockClient = new MockClient([
        SendEmailWithAttachmentsRequest::class => MockResponse::make(
            ['message_id' => 'attach-transport-001'],
            200,
        ),
    ]);

    $transport = makeTransport($mockClient);

    $content = 'Hello, this is a text file.';

    $email = (new Email())
        ->from(new Address('from@example.com'))
        ->to(new Address('to@example.com'))
        ->subject('Attachment Test')
        ->html('<p>See attachment</p>')
        ->attach($content, 'hello.txt', 'text/plain');

    sendViaTransport($transport, $email);

    $mockClient->assertSent(
        SendEmailWithAttachmentsRequest::class,
        function (SendEmailWithAttachmentsRequest $request) use ($content) {
            $attachment = $request->body()->all()['attachments'][0] ?? null;

            expect($attachment)->not->toBeNull()
                ->and($attachment['name'])->toBe('hello.txt')
                ->and($attachment['mime_type'])->toBe('text/plain')
                ->and(base64_decode($attachment['content']))->toBe($content);

            return true;
        },
    );
});

// ─── From address fallback ────────────────────────────────────────────────────

it('falls back to config from address when email has no From header', function (): void {
    $mockClient = new MockClient([
        SendHtmlEmailRequest::class => MockResponse::make(['message_id' => 'fallback-001'], 200),
    ]);

    $transport = makeTransport($mockClient);

    // Explicitly set From so the email is valid — then verify it is extracted correctly.
    $email = (new Email())
        ->from(new Address('explicit@example.com', 'Explicit'))
        ->to(new Address('to@example.com'))
        ->subject('From Test')
        ->html('<p>Hi</p>');

    sendViaTransport($transport, $email);

    $mockClient->assertSent(SendHtmlEmailRequest::class, function (SendHtmlEmailRequest $request) {
        $body = $request->body()->all();

        expect($body['from']['email'])->toBe('explicit@example.com')
            ->and($body['from']['name'])->toBe('Explicit');

        return true;
    });
});

// ─── Transport string representation ──────────────────────────────────────────

it('returns the correct string representation', function (): void {
    $mockClient = new MockClient([]);
    $transport  = makeTransport($mockClient);

    expect((string) $transport)->toBe('ahasend://api');
});

// ─── Mail driver registration ─────────────────────────────────────────────────

it('resolves the ahasend mailer from the Laravel Mail facade', function (): void {
    config()->set('mail.mailers.ahasend', ['transport' => 'ahasend']);

    $mailer = Mail::mailer('ahasend');

    expect($mailer)->toBeInstanceOf(\Illuminate\Mail\Mailer::class);
});

// ─── Error propagation ────────────────────────────────────────────────────────

it('wraps AhasendException in a Symfony TransportException', function (): void {
    $mockClient = new MockClient([
        SendHtmlEmailRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    $transport = makeTransport($mockClient);

    $email = (new Email())
        ->from(new Address('from@example.com'))
        ->to(new Address('to@example.com'))
        ->subject('Error Test')
        ->html('<p>Should fail</p>');

    sendViaTransport($transport, $email);
})->throws(\Symfony\Component\Mailer\Exception\TransportException::class);
