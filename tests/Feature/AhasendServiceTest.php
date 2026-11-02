<?php

declare(strict_types=1);

use GraystackIT\Ahasend\AhasendService;
use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Events\MailSent;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Models\AhasendMessage;
use GraystackIT\Ahasend\Requests\SendConversationalEmailRequest;
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
            sendResponse('ahasend-msg-001'),
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
            sendResponse('html-msg-002'),
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
            sendResponse('attach-msg-003'),
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

it('routes to SendConversationalEmailRequest when CC is present', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendConversationalEmailRequest::class => MockResponse::make(
            sendResponse('conv-msg-004'),
            200,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $ahasendId = $service->sendHtml(
        to:          [['email' => 'to@example.com']],
        subject:     'CC Test',
        htmlContent: '<p>Hello</p>',
        cc:          [['email' => 'cc@example.com']],
    );

    expect($ahasendId)->toBe('conv-msg-004');
});

it('routes to SendConversationalEmailRequest when BCC is present', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendConversationalEmailRequest::class => MockResponse::make(
            sendResponse('conv-msg-005'),
            200,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $ahasendId = $service->sendText(
        to:          [['email' => 'to@example.com']],
        subject:     'BCC Test',
        textContent: 'Hello',
        bcc:         [['email' => 'bcc@example.com']],
    );

    expect($ahasendId)->toBe('conv-msg-005');
});

// ─── Templates ────────────────────────────────────────────────────────────

it('sends a templated email via sendTemplate()', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendEmailRequest::class => MockResponse::make(sendResponse('tmpl-msg-001'), 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    $ahasendId = $service->sendTemplate(
        to:            [['email' => 'to@example.com']],
        templateId:    'tmpl-uuid-1',
        substitutions: ['name' => 'Alice'],
    );

    expect($ahasendId)->toBe('tmpl-msg-001');

    $lastRequest = $mockClient->getLastRequest();
    expect($lastRequest)->toBeInstanceOf(SendEmailRequest::class);

    $reflection = new ReflectionMethod($lastRequest, 'defaultBody');
    $body       = $reflection->invoke($lastRequest);

    expect($body['template_id'])->toBe('tmpl-uuid-1')
        ->and($body['substitutions'])->toBe(['name' => 'Alice']);
});

it('throws AhasendException when templateId is combined with htmlContent', function (): void {
    $service = new AhasendService(app(AhasendConnector::class));

    $service->send(new EmailMessage(
        fromEmail:   'a@example.com',
        fromName:    'A',
        to:          [['email' => 'b@example.com']],
        subject:     'Subject',
        htmlContent: '<p>Hi</p>',
        templateId:  'tmpl-uuid-1',
    ));
})->throws(AhasendException::class);

it('throws AhasendException when templateId is combined with cc/bcc', function (): void {
    $service = new AhasendService(app(AhasendConnector::class));

    $service->send(new EmailMessage(
        fromEmail:  'a@example.com',
        fromName:   'A',
        to:         [['email' => 'b@example.com']],
        subject:    'Subject',
        cc:         [['email' => 'cc@example.com']],
        templateId: 'tmpl-uuid-1',
    ));
})->throws(AhasendException::class);

// ─── Attachment payload fixes ─────────────────────────────────────────────

it('never sends cc/bcc on the plain messages endpoint, since Ahasend does not support them there', function (): void {
    Event::fake([MailSent::class]);

    $mockClient = new MockClient([
        SendEmailWithAttachmentsRequest::class => MockResponse::make(sendResponse('attach-msg-004'), 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new AhasendService($connector);

    // Constructing the request directly (bypassing AhasendService::resolveRequest's cc/bcc
    // routing) to prove the class itself no longer builds cc/bcc into the body at all.
    $request = new SendEmailWithAttachmentsRequest(new EmailMessage(
        fromEmail:   'a@example.com',
        fromName:    'A',
        to:          [['email' => 'b@example.com']],
        subject:     'Subject',
        cc:          [['email' => 'cc@example.com']],
        attachments: [['name' => 'a.txt', 'content' => base64_encode('hi'), 'mime_type' => 'text/plain']],
    ));

    $reflection = new ReflectionMethod($request, 'defaultBody');
    $body       = $reflection->invoke($request);

    expect($body)->not->toHaveKey('cc')
        ->and($body)->not->toHaveKey('bcc');
});

it('passes content_id and content_disposition through on attachments', function (): void {
    $request = new SendEmailWithAttachmentsRequest(new EmailMessage(
        fromEmail: 'a@example.com',
        fromName:  'A',
        to:        [['email' => 'b@example.com']],
        subject:   'Subject',
        attachments: [[
            'name'                => 'inline.png',
            'content'             => base64_encode('binary'),
            'mime_type'           => 'image/png',
            'content_id'          => '<image1@example.com>',
            'content_disposition' => 'inline',
        ]],
    ));

    $reflection = new ReflectionMethod($request, 'defaultBody');
    $body       = $reflection->invoke($request);

    expect($body['attachments'][0]['content_id'])->toBe('<image1@example.com>')
        ->and($body['attachments'][0]['content_disposition'])->toBe('inline');
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

// ─── The id the API assigns ───────────────────────────────────────────────

/**
 * Send one plain-text mail against a mocked response body and return the id.
 *
 * @param  array<string, mixed>  $body
 */
function sendAgainst(array $body, ?string $clientId = null): string
{
    $connector = app(AhasendConnector::class);
    $connector->withMockClient(new MockClient([SendEmailRequest::class => MockResponse::make($body, 202)]));

    return (new AhasendService($connector))->send(new EmailMessage(
        fromEmail:   'a@example.com',
        fromName:    'A',
        to:          [['email' => 'b@example.com']],
        subject:     'Id test',
        textContent: 'body',
        messageId:   $clientId,
    ));
}

it('returns the bare UUID the webhooks carry as data.id, from 9 November 2026', function (): void {
    $response = sendResponse('0192f5a4-7b1c-7d3e-9a2b-4c5d6e7f8a9b');
    $response['data'][0]['message_id_header'] = '<0192f5a4-0000-7000-8000-000000000000@example.com>';

    expect(sendAgainst($response))->toBe('0192f5a4-7b1c-7d3e-9a2b-4c5d6e7f8a9b');
});

it('returns the bracketed Message-ID header the API answered with before the switch', function (): void {
    expect(sendAgainst(sendResponse('<0192f5a4-0000-7000-8000-000000000000@example.com>')))
        ->toBe('<0192f5a4-0000-7000-8000-000000000000@example.com>');
});

it('reads the first recipient of a multi-recipient response', function (): void {
    $response = sendResponse('first-id');
    $response['data'][] = sendResponse('second-id', 'other@example.com')['data'][0];

    expect(sendAgainst($response))->toBe('first-id');
});

it('falls back to the client-side id only when the response carries no recipient list', function (): void {
    expect(sendAgainst([], 'client-id'))->toBe('client-id');
});

it('throws instead of reporting a send that every recipient was refused', function (): void {
    Event::fake([MailSent::class]);

    expect(fn () => sendAgainst(sendResponse(null)))
        ->toThrow(AhasendException::class, 'to@example.com: Recipient is suppressed');

    Event::assertNotDispatched(MailSent::class);
});

it('returns the first accepted recipient when another one was refused', function (): void {
    $response = sendResponse(null);
    $response['data'][] = sendResponse('accepted-id', 'other@example.com')['data'][0];

    expect(sendAgainst($response))->toBe('accepted-id');
});

it('keeps the id of every accepted recipient, on the event and in storage', function (): void {
    Event::fake([MailSent::class]);
    config(['ahasend.store_logs' => true, 'ahasend.storage_driver' => 'database']);

    $response = sendResponse('first-id');
    $response['data'][] = sendResponse('second-id', 'other@example.com')['data'][0];

    sendAgainst($response);

    Event::assertDispatched(MailSent::class, fn (MailSent $event): bool => $event->recipientMessageIds === [
        'to@example.com'    => 'first-id',
        'other@example.com' => 'second-id',
    ]);

    expect(AhasendMessage::query()->orderBy('message_id')->pluck('recipient', 'message_id')->all())
        ->toBe(['first-id' => 'to@example.com', 'second-id' => 'other@example.com']);
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
