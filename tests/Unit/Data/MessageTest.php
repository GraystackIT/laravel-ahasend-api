<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\Message;
use GraystackIT\Ahasend\Enums\MessageStatus;

it('constructs a Message from an API response array', function (): void {
    $message = Message::fromArray([
        'id'           => 'msg-abc123',
        'subject'      => 'Hello World',
        'sender'       => 'sender@example.com',
        'recipient'    => 'recipient@example.com',
        'status'       => 'delivered',
        'tags'         => ['welcome'],
        'sent_at'      => '2024-01-01T12:00:00Z',
        'delivered_at' => '2024-01-01T12:00:05Z',
        'created_at'   => '2024-01-01T11:00:00Z',
        'updated_at'   => '2024-01-01T12:00:05Z',
    ]);

    expect($message->id)->toBe('msg-abc123')
        ->and($message->subject)->toBe('Hello World')
        ->and($message->sender)->toBe('sender@example.com')
        ->and($message->recipient)->toBe('recipient@example.com')
        ->and($message->status)->toBe(MessageStatus::Delivered)
        ->and($message->tags)->toBe(['welcome'])
        ->and($message->sentAt)->toBe('2024-01-01T12:00:00Z')
        ->and($message->deliveredAt)->toBe('2024-01-01T12:00:05Z');
});

it('falls back to message_id field when id is absent', function (): void {
    $message = Message::fromArray([
        'message_id' => 'fallback-id',
        'subject'    => 'Test',
        'sender'     => 'a@b.com',
        'recipient'  => 'c@d.com',
        'status'     => 'queued',
    ]);

    expect($message->id)->toBe('fallback-id');
});

it('serializes a Message to array', function (): void {
    $message = Message::fromArray([
        'id'        => 'msg-1',
        'subject'   => 'Test Subject',
        'sender'    => 'from@example.com',
        'recipient' => 'to@example.com',
        'status'    => 'queued',
    ]);

    $array = $message->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe('msg-1')
        ->and($array['status'])->toBe('queued')
        ->and($array['subject'])->toBe('Test Subject');
});

it('identifies terminal statuses', function (): void {
    expect(MessageStatus::Delivered->isTerminal())->toBeTrue()
        ->and(MessageStatus::Failed->isTerminal())->toBeTrue()
        ->and(MessageStatus::Bounced->isTerminal())->toBeTrue()
        ->and(MessageStatus::Suppressed->isTerminal())->toBeTrue()
        ->and(MessageStatus::Queued->isTerminal())->toBeFalse()
        ->and(MessageStatus::Scheduled->isTerminal())->toBeFalse()
        ->and(MessageStatus::Deferred->isTerminal())->toBeFalse();
});
