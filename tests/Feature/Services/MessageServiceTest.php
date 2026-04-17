<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Message;
use GraystackIT\Ahasend\Enums\MessageStatus;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Messages\CancelScheduledMessageRequest;
use GraystackIT\Ahasend\Requests\Messages\GetMessageRequest;
use GraystackIT\Ahasend\Requests\Messages\ListMessagesRequest;
use GraystackIT\Ahasend\Services\MessageService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ─── Container resolution ─────────────────────────────────────────────────

it('resolves MessageService from the container', function (): void {
    expect(app(MessageService::class))->toBeInstanceOf(MessageService::class);
});

// ─── get() ────────────────────────────────────────────────────────────────

it('fetches a single message by ID', function (): void {
    $mockClient = new MockClient([
        GetMessageRequest::class => MockResponse::make([
            'id'      => 'msg-001',
            'subject' => 'Hello',
            'from'    => ['email' => 'sender@example.com', 'name' => 'Sender'],
            'to'      => [['email' => 'to@example.com']],
            'status'  => 'delivered',
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $message = $service->get('msg-001');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->id)->toBe('msg-001')
        ->and($message->status)->toBe(MessageStatus::Delivered)
        ->and($message->subject)->toBe('Hello');
});

it('throws AhasendException when message is not found', function (): void {
    $mockClient = new MockClient([
        GetMessageRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $service->get('nonexistent-id');
})->throws(AhasendException::class);

it('throws InvalidArgumentException for empty message ID in get', function (): void {
    $service = new MessageService(app(AhasendConnector::class));
    $service->get('');
})->throws(\InvalidArgumentException::class);

// ─── list() ───────────────────────────────────────────────────────────────

it('lists messages and returns Message DTOs', function (): void {
    $mockClient = new MockClient([
        ListMessagesRequest::class => MockResponse::make([
            'data' => [
                [
                    'id'      => 'msg-001',
                    'subject' => 'First',
                    'from'    => ['email' => 'a@example.com', 'name' => 'A'],
                    'to'      => [['email' => 'b@example.com']],
                    'status'  => 'sent',
                ],
                [
                    'id'      => 'msg-002',
                    'subject' => 'Second',
                    'from'    => ['email' => 'a@example.com', 'name' => 'A'],
                    'to'      => [['email' => 'c@example.com']],
                    'status'  => 'delivered',
                ],
            ],
            'meta' => ['total' => 2, 'page' => 1, 'per_page' => 25],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $result  = $service->list();

    expect($result['data'])->toHaveCount(2)
        ->and($result['data'][0])->toBeInstanceOf(Message::class)
        ->and($result['data'][0]->id)->toBe('msg-001')
        ->and($result['data'][1]->status)->toBe(MessageStatus::Delivered)
        ->and($result['meta']['total'])->toBe(2);
});

it('filters messages by status', function (): void {
    $mockClient = new MockClient([
        ListMessagesRequest::class => MockResponse::make([
            'data' => [],
            'meta' => [],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $result  = $service->list(status: MessageStatus::Bounced);

    expect($result['data'])->toBeEmpty();
});

it('throws InvalidArgumentException for invalid page in list', function (): void {
    $service = new MessageService(app(AhasendConnector::class));
    $service->list(page: 0);
})->throws(\InvalidArgumentException::class);

it('throws AhasendException on API error when listing messages', function (): void {
    $mockClient = new MockClient([
        ListMessagesRequest::class => MockResponse::make(['error' => 'Server error'], 500),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $service->list();
})->throws(AhasendException::class);

// ─── cancel() ─────────────────────────────────────────────────────────────

it('cancels a scheduled message', function (): void {
    $mockClient = new MockClient([
        CancelScheduledMessageRequest::class => MockResponse::make([], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $result  = $service->cancel('msg-scheduled-001');

    expect($result)->toBeTrue();
});

it('throws InvalidArgumentException for empty message ID in cancel', function (): void {
    $service = new MessageService(app(AhasendConnector::class));
    $service->cancel('');
})->throws(\InvalidArgumentException::class);

it('throws AhasendException when cancelling a non-scheduled message', function (): void {
    $mockClient = new MockClient([
        CancelScheduledMessageRequest::class => MockResponse::make(
            ['error' => 'Message cannot be cancelled'],
            422,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new MessageService($connector);
    $service->cancel('msg-already-sent');
})->throws(AhasendException::class);
