<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\EmailMessage;

it('constructs with all required fields', function (): void {
    $message = new EmailMessage(
        fromEmail:   'sender@example.com',
        fromName:    'Sender',
        to:          [['email' => 'recipient@example.com', 'name' => 'Recipient']],
        subject:     'Hello World',
        textContent: 'Plain text body',
    );

    expect($message->fromEmail)->toBe('sender@example.com')
        ->and($message->fromName)->toBe('Sender')
        ->and($message->subject)->toBe('Hello World')
        ->and($message->textContent)->toBe('Plain text body')
        ->and($message->htmlContent)->toBeNull()
        ->and($message->cc)->toBeEmpty()
        ->and($message->bcc)->toBeEmpty()
        ->and($message->attachments)->toBeEmpty()
        ->and($message->messageId)->toBeNull();
});

it('constructs from array via fromArray factory', function (): void {
    $message = EmailMessage::fromArray([
        'from_email'   => 'a@example.com',
        'from_name'    => 'Alice',
        'to'           => [['email' => 'b@example.com']],
        'subject'      => 'Test Subject',
        'html_content' => '<p>Hello</p>',
        'text_content' => 'Hello',
        'cc'           => [['email' => 'c@example.com']],
        'bcc'          => [['email' => 'd@example.com']],
        'attachments'  => [],
        'message_id'   => 'uuid-1234',
    ]);

    expect($message->fromEmail)->toBe('a@example.com')
        ->and($message->htmlContent)->toBe('<p>Hello</p>')
        ->and($message->messageId)->toBe('uuid-1234')
        ->and($message->cc)->toHaveCount(1)
        ->and($message->bcc)->toHaveCount(1);
});

it('applies defaults for missing fromArray keys', function (): void {
    $message = EmailMessage::fromArray([
        'to'      => [['email' => 'x@example.com']],
        'subject' => 'Minimal',
    ]);

    expect($message->fromEmail)->toBe('')
        ->and($message->fromName)->toBe('')
        ->and($message->cc)->toBeEmpty()
        ->and($message->attachments)->toBeEmpty()
        ->and($message->messageId)->toBeNull();
});

it('serializes to array via toArray', function (): void {
    $message = new EmailMessage(
        fromEmail:   'sender@example.com',
        fromName:    'Sender',
        to:          [['email' => 'r@example.com']],
        subject:     'Subject',
        htmlContent: '<b>Hi</b>',
        messageId:   'my-id',
    );

    $array = $message->toArray();

    expect($array['from_email'])->toBe('sender@example.com')
        ->and($array['html_content'])->toBe('<b>Hi</b>')
        ->and($array['message_id'])->toBe('my-id')
        ->and($array)->toHaveKey('to')
        ->and($array)->toHaveKey('cc')
        ->and($array)->toHaveKey('bcc')
        ->and($array)->toHaveKey('attachments');
});
