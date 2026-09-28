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
        ->and($array['raw_status'])->toBe('queued')
        ->and($array['subject'])->toBe('Test Subject');
});

it('identifies terminal statuses', function (): void {
    expect(MessageStatus::Delivered->isTerminal())->toBeTrue()
        ->and(MessageStatus::Failed->isTerminal())->toBeTrue()
        ->and(MessageStatus::Bounced->isTerminal())->toBeTrue()
        ->and(MessageStatus::Suppressed->isTerminal())->toBeTrue()
        ->and(MessageStatus::Queued->isTerminal())->toBeFalse()
        ->and(MessageStatus::Scheduled->isTerminal())->toBeFalse()
        ->and(MessageStatus::Deferred->isTerminal())->toBeFalse()
        ->and(MessageStatus::Received->isTerminal())->toBeFalse()
        ->and(MessageStatus::Unknown->isTerminal())->toBeFalse();
});

it('falls back to Unknown instead of throwing on an unrecognized status, since Ahasend documents status as an open set of values', function (): void {
    $message = Message::fromArray([
        'id'        => 'msg-1',
        'subject'   => 'Test',
        'sender'    => 'a@b.com',
        'recipient' => 'c@d.com',
        'status'    => 'sandbox_delivered',
    ]);

    expect($message->status)->toBe(MessageStatus::Unknown)
        ->and($message->rawStatus)->toBe('sandbox_delivered');
});

it('parses the newly documented Message fields', function (): void {
    $message = Message::fromArray([
        'id'                     => 'msg-1',
        'subject'                => 'Test',
        'sender'                 => 'a@b.com',
        'recipient'              => 'c@d.com',
        'status'                 => 'bounced',
        'direction'              => 'outbound',
        'num_attempts'           => 3,
        'delivery_attempts'      => [['time' => '2026-01-01T00:00:00Z', 'log' => 'x', 'status' => 'bounced']],
        'is_bounce_notification' => true,
        'bounce_classification'  => 'InvalidRecipient',
        'reference_message_id'   => 42,
        'click_count'            => 2,
        'open_count'             => 5,
        'content'                => 'raw rfc822',
        'retain_until'           => '2026-02-01T00:00:00Z',
    ]);

    expect($message->direction)->toBe('outbound')
        ->and($message->numAttempts)->toBe(3)
        ->and($message->deliveryAttempts)->toHaveCount(1)
        ->and($message->isBounceNotification)->toBeTrue()
        ->and($message->bounceClassification)->toBe('InvalidRecipient')
        ->and($message->referenceMessageId)->toBe(42)
        ->and($message->clickCount)->toBe(2)
        ->and($message->openCount)->toBe(5)
        ->and($message->content)->toBe('raw rfc822')
        ->and($message->retainUntil)->toBe('2026-02-01T00:00:00Z');
});
