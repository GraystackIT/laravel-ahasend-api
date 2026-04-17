<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\Message;
use GraystackIT\Ahasend\Enums\MessageStatus;

it('constructs a Message from an API response array', function (): void {
    $message = Message::fromArray([
        'id'           => 'msg-abc123',
        'subject'      => 'Hello World',
        'from'         => ['email' => 'sender@example.com', 'name' => 'Sender'],
        'to'           => [['email' => 'recipient@example.com', 'name' => 'Recipient']],
        'status'       => 'delivered',
        'cc'           => [],
        'scheduled_at' => null,
        'sent_at'      => '2024-01-01T12:00:00Z',
        'created_at'   => '2024-01-01T11:00:00Z',
    ]);

    expect($message->id)->toBe('msg-abc123')
        ->and($message->subject)->toBe('Hello World')
        ->and($message->fromEmail)->toBe('sender@example.com')
        ->and($message->fromName)->toBe('Sender')
        ->and($message->status)->toBe(MessageStatus::Delivered)
        ->and($message->sentAt)->toBe('2024-01-01T12:00:00Z');
});

it('falls back to message_id field when id is absent', function (): void {
    $message = Message::fromArray([
        'message_id' => 'fallback-id',
        'subject'    => 'Test',
        'from'       => ['email' => 'a@b.com', 'name' => 'A'],
        'to'         => [['email' => 'c@d.com']],
        'status'     => 'sent',
    ]);

    expect($message->id)->toBe('fallback-id');
});

it('serializes a Message to array', function (): void {
    $message = Message::fromArray([
        'id'      => 'msg-1',
        'subject' => 'Test Subject',
        'from'    => ['email' => 'from@example.com', 'name' => 'From'],
        'to'      => [['email' => 'to@example.com']],
        'status'  => 'sent',
    ]);

    $array = $message->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe('msg-1')
        ->and($array['status'])->toBe('sent')
        ->and($array['subject'])->toBe('Test Subject');
});

it('identifies terminal statuses', function (): void {
    expect(MessageStatus::Delivered->isTerminal())->toBeTrue()
        ->and(MessageStatus::Failed->isTerminal())->toBeTrue()
        ->and(MessageStatus::Bounced->isTerminal())->toBeTrue()
        ->and(MessageStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(MessageStatus::Queued->isTerminal())->toBeFalse()
        ->and(MessageStatus::Sent->isTerminal())->toBeFalse();
});
