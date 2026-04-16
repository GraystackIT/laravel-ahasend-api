<?php

declare(strict_types=1);

use GraystackIT\Ahasend\AhasendService;
use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Events\MailSent;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\SendEmailRequest;
use GraystackIT\Ahasend\Requests\SendEmailWithAttachmentsRequest;
use GraystackIT\Ahasend\Requests\SendHtmlEmailRequest;
use Illuminate\Support\Facades\Event;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ─── Container resolution ─────────────────────────────────────────────────

it('resolves AhasendService from the container', function (): void {
    expect(app(AhasendService::class))->toBeInstanceOf(AhasendService::class);
});

it('resolves AhasendConnector from the container', function (): void {
    expect(app(AhasendConnector::class))->toBeInstanceOf(AhasendConnector::class);
});

// ─── Successful send ──────────────────────────────────────────────────────

it('sends a plain-text email successfully', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendEmailRequest::class => MockResponse::make(
            ['message_id' => 'ahasend-msg-001'],
            200,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $ahasendId = $service->sendText(
        to:          [['email' => 'to@example.com', 'name' => 'Recipient']],
        subject:     'Hello',
        textContent: 'Plain text body',
    );

    expect($ahasendId)->toBe('ahasend-msg-001');

    Event::assertDispatched(MailSent::class, function (MailSent $event) use ($ahasendId): bool {
        return $event->ahasendMessageId === $ahasendId;
    });
});

it('sends an HTML email and selects SendHtmlEmailRequest', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendHtmlEmailRequest::class => MockResponse::make(
            ['message_id' => 'html-msg-002'],
            200,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $ahasendId = $service->sendHtml(
        to:          [['email' => 'to@example.com']],
        subject:     'HTML Email',
        htmlContent: '<p>Hello</p>',
    );

    expect($ahasendId)->toBe('html-msg-002');
});

it('sends an email with attachments and selects SendEmailWithAttachmentsRequest', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendEmailWithAttachmentsRequest::class => MockResponse::make(
            ['message_id' => 'attach-msg-003'],
            200,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $ahasendId = $service->sendWithAttachments(
        to:          [['email' => 'to@example.com']],
        subject:     'With Attachment',
        attachments: [
            [
                'name'      => 'test.txt',
                'content'   => base64_encode('Hello attachment'),
                'mime_type' => 'text/plain',
            ],
        ],
        textContent: 'See attachment.',
    );

    expect($ahasendId)->toBe('attach-msg-003');
});

// ─── Auto-generated message_id ────────────────────────────────────────────

it('generates a message_id when none is provided', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendEmailRequest::class => MockResponse::make([], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $service->send(new EmailMessage(
        fromEmail:   'a@example.com',
        fromName:    'A',
        to:          [['email' => 'b@example.com']],
        subject:     'Auto-ID test',
        textContent: 'body',
    ));

    Event::assertDispatched(MailSent::class, function (MailSent $event): bool {
        return $event->message->messageId !== null && strlen($event->message->messageId) > 0;
    });
});

// ─── Error handling ───────────────────────────────────────────────────────

it('throws AhasendException on 4xx API response', function (): void {
    $mockClient = new MockClient([
        SendEmailRequest::class => MockResponse::make(
            ['error' => 'Unauthorized'],
            401,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $service->sendText(
        to:          [['email' => 'to@example.com']],
        subject:     'Should fail',
        textContent: 'body',
    );
})->throws(AhasendException::class);

it('throws AhasendException on 5xx API response', function (): void {
    $mockClient = new MockClient([
        SendEmailRequest::class => MockResponse::make(
            ['error' => 'Internal server error'],
            500,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $service->sendText(
        to:          [['email' => 'to@example.com']],
        subject:     'Should fail',
        textContent: 'body',
    );
})->throws(AhasendException::class);
