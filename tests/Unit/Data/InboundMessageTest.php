<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\InboundMessage;

// ─── Threading ────────────────────────────────────────────────────────────

it('strips the angle brackets from message ids', function (): void {
    $message = InboundMessage::fromArray([
        'in_reply_to' => '<token-abc@mail.graystack.one>',
    ]);

    expect($message->inReplyTo)->toBe('token-abc@mail.graystack.one');
});

it('parses a space-separated References header', function (): void {
    $message = InboundMessage::fromArray([
        'references' => '<a@example.com>  <b@example.com>   <c@example.com>',
    ]);

    expect($message->references)->toBe(['a@example.com', 'b@example.com', 'c@example.com']);
});

it('also accepts References as an array', function (): void {
    $message = InboundMessage::fromArray([
        'references' => ['<a@example.com>', '<b@example.com>'],
    ]);

    expect($message->references)->toBe(['a@example.com', 'b@example.com']);
});

it('offers parent ids most specific first', function (): void {
    // References runs oldest to newest, so the newest is the closest parent
    // after In-Reply-To itself.
    $message = InboundMessage::fromArray([
        'in_reply_to' => '<direct@example.com>',
        'references'  => '<oldest@example.com> <middle@example.com> <newest@example.com>',
    ]);

    expect($message->parentMessageIds())->toBe([
        'direct@example.com',
        'newest@example.com',
        'middle@example.com',
        'oldest@example.com',
    ]);
});

it('does not repeat an id that appears in both headers', function (): void {
    $message = InboundMessage::fromArray([
        'in_reply_to' => '<same@example.com>',
        'references'  => '<older@example.com> <same@example.com>',
    ]);

    expect($message->parentMessageIds())->toBe(['same@example.com', 'older@example.com']);
});

it('reports no parents for a fresh message', function (): void {
    expect(InboundMessage::fromArray([])->parentMessageIds())->toBe([]);
});

// ─── Addresses ────────────────────────────────────────────────────────────

it('normalises recipients given as a comma-separated string', function (): void {
    $message = InboundMessage::fromArray([
        'to' => 'a@example.com, b@example.com',
    ]);

    expect($message->to)->toBe(['a@example.com', 'b@example.com']);
});

it('normalises recipients given as objects', function (): void {
    $message = InboundMessage::fromArray([
        'to' => [['email' => 'a@example.com'], ['email' => 'b@example.com']],
    ]);

    expect($message->to)->toBe(['a@example.com', 'b@example.com']);
});

it('drops empty recipient entries', function (): void {
    $message = InboundMessage::fromArray(['to' => 'a@example.com, , b@example.com']);

    expect($message->to)->toBe(['a@example.com', 'b@example.com']);
});

// ─── Automation detection ─────────────────────────────────────────────────

it('treats Auto-Submitted other than "no" as automated', function (): void {
    expect(InboundMessage::fromArray(['auto_submitted' => 'auto-replied'])->isAutomated())->toBeTrue()
        ->and(InboundMessage::fromArray(['auto_submitted' => 'no'])->isAutomated())->toBeFalse()
        ->and(InboundMessage::fromArray([])->isAutomated())->toBeFalse();
});

it('treats bulk and list precedence as automated', function (string $precedence): void {
    $message = InboundMessage::fromArray(['headers' => ['Precedence' => $precedence]]);

    expect($message->isAutomated())->toBeTrue();
})->with(['bulk', 'list', 'junk', 'Bulk']);

it('treats a List-Id header as automated', function (): void {
    $message = InboundMessage::fromArray([
        'headers' => ['List-Id' => '<newsletter.example.com>'],
    ]);

    expect($message->isAutomated())->toBeTrue();
});

it('treats X-Auto-Response-Suppress as automated', function (): void {
    $message = InboundMessage::fromArray([
        'headers' => ['X-Auto-Response-Suppress' => 'All'],
    ]);

    expect($message->isAutomated())->toBeTrue();
});

it('looks headers up case-insensitively', function (): void {
    $message = InboundMessage::fromArray([
        'headers' => ['MESSAGE-ID' => '<abc@example.com>'],
    ]);

    expect($message->header('Message-Id'))->toBe('<abc@example.com>')
        ->and($message->header('Absent'))->toBeNull();
});

// ─── Bodies ───────────────────────────────────────────────────────────────

it('prefers the reply-stripped body over the full one', function (): void {
    $message = InboundMessage::fromArray([
        'plain_body'            => "Neu\n\n> Zitat",
        'reply_from_plain_body' => 'Neu',
    ]);

    expect($message->text())->toBe('Neu');
});

it('falls back to the full plain body when replies were not stripped', function (): void {
    $message = InboundMessage::fromArray(['plain_body' => 'Ganzer Text']);

    expect($message->text())->toBe('Ganzer Text');
});

// ─── Attachments ──────────────────────────────────────────────────────────

it('decodes attachment contents', function (): void {
    $message = InboundMessage::fromArray([
        'attachments' => [[
            'file_name'    => 'note.txt',
            'content_type' => 'text/plain',
            'data'         => base64_encode('Hallo'),
        ]],
    ]);

    expect($message->attachments[0]->fileName)->toBe('note.txt')
        ->and($message->attachments[0]->contents())->toBe('Hallo')
        ->and($message->attachments[0]->inline)->toBeFalse();
});

it('marks parts with a content id as inline', function (): void {
    $message = InboundMessage::fromArray([
        'attachments' => [
            ['file_name' => 'logo.png', 'content_id' => '<logo123>', 'data' => base64_encode('PNG')],
            ['file_name' => 'doc.pdf', 'data' => base64_encode('PDF')],
        ],
    ]);

    expect($message->inlineAttachments())->toHaveCount(1)
        ->and($message->attachments[0]->contentId)->toBe('logo123')
        ->and($message->attachments[0]->inline)->toBeTrue();
});

it('keeps the raw payload available', function (): void {
    $message = InboundMessage::fromArray(['subject' => 'Test', 'unmapped_field' => 42]);

    expect($message->raw['unmapped_field'])->toBe(42);
});
