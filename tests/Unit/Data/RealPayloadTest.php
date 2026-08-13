<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Events\MailBounced;

/**
 * These fixtures are real Ahasend payloads, captured from live mail and
 * anonymised. They exist because several assumptions about the payload shape
 * turned out to be wrong when checked against reality — the tests below pin
 * down what Ahasend actually sends.
 *
 * @return array<string, mixed>
 */
function payloadFixture(string $name): array
{
    $path = __DIR__ . '/../../Fixtures/Payloads/' . $name . '.json';
    $file = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return $file['payload']['data'] ?? $file['payload'];
}

it('reads the routed recipient from the top-level to field', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('reply-to-ticket-address'));

    // Ahasend sends no `recipient` key; the routed address is the payload's
    // `to`, while the actual To header sits in `headers`.
    expect($message->recipient)->toBe('support.graystack@tickets.graystack.one')
        ->and($message->header('To'))->toContain('support.graystack@tickets.graystack.one');
});

it('carries a BCC recipient that appears in no header', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('reply-delivered-via-bcc'));

    // Same mail as the fixture above, delivered on the other route. The BCC'd
    // address is in the payload's `to` but nowhere in the headers — keying on
    // the To header alone would lose this delivery.
    expect($message->recipient)->toBe('rechnungen.testunternehmen@postbox.graystack.one')
        ->and($message->header('To'))->not->toContain('rechnungen.testunternehmen');
});

it('gives each delivery of one mail its own routed recipient', function (): void {
    $ticket  = InboundMessage::fromArray(payloadFixture('reply-to-ticket-address'));
    $postbox = InboundMessage::fromArray(payloadFixture('reply-delivered-via-bcc'));

    // One mail, two routes, two deliveries: the sender's Message-ID is shared,
    // the routed recipient is not.
    expect($ticket->messageId)->toBe($postbox->messageId)
        ->and($ticket->recipient)->not->toBe($postbox->recipient);
});

it('returns the new reply from reply_from_plain_body, not the quoted history', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('reply-to-ticket-address'));

    expect($message->text())->toBe('Noch ein testmail, diesmal mit bcc')
        ->and($message->plainBody)->toContain('>')          // the quote is in the full body
        ->and($message->text())->not->toContain('>');
});

it('recovers the parent message id a reply refers to', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('reply-to-ticket-address'));

    // This is the id Ahasend assigned to our outbound mail — the anchor the
    // whole threading hangs on.
    expect($message->parentMessageIds())
        ->toBe(['019ffc0c-1fda-748b-a26f-799f08e4d54b@tickets.graystack.one']);
});

it('keeps the subject marker through the client Re: prefix', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('reply-to-ticket-address'));

    expect($message->subject)->toBe('Re: Testvorgang [#T-1042] — bitte antworten');
});

it('provides a spam score and no automation markers on a human reply', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('reply-to-ticket-address'));

    expect($message->spamScore)->toBe(0.0)
        ->and($message->bounce)->toBeFalse()
        ->and($message->isAutomated())->toBeFalse();
});

it('reads attachment metadata under the names Ahasend actually uses', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('inbound-with-attachments'));

    // `filename`, `content_type`, `data`, `disposition` — not `file_name`,
    // `mime_type` or `content`, which earlier guesses had assumed.
    expect($message->attachments)->toHaveCount(4)
        ->and($message->attachments[2]->fileName)->toBe('Biohort_ATAA93AD.pdf')
        ->and($message->attachments[2]->contentType)->toBe('application/pdf')
        ->and($message->attachments[3]->contentType)->toBe('application/zip');
});

it('treats plain attachments as not inline even without a content id', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('inbound-with-attachments'));

    expect($message->inlineAttachments())->toBeEmpty();

    foreach ($message->attachments as $attachment) {
        expect($attachment->inline)->toBeFalse()
            ->and($attachment->contentId)->toBeNull();
    }
});

it('recognises an inline image by its disposition and links it to the body', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('inbound-with-inline-image'));

    $inline = $message->inlineAttachments();

    expect($inline)->toHaveCount(1)
        ->and($inline[0]->contentId)->toBe('a229a73a9fca82db075c3a8397e297b1@infomaniak')
        // The body references exactly that id, which is what makes rewriting
        // `cid:` links to stored documents possible later on.
        ->and($message->htmlBody)->toContain('cid:' . $inline[0]->contentId);
});

it('recognises a real out-of-office reply as automated', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('inbound-auto-responder'));

    // Ahasend fills the top-level `auto_submitted` (null on human mail) and
    // passes the headers through. Both matter: an auto-reply that is not
    // recognised gets answered by our auto-reply, and the two then bounce back
    // and forth without end.
    expect($message->isAutomated())->toBeTrue()
        ->and($message->autoSubmitted)->toBe('auto-generated')
        ->and($message->header('Auto-Submitted'))->toBe('auto-generated')
        ->and($message->header('X-Auto-Response-Suppress'))->toBe('All');
});

it('threads an auto-reply like any other reply', function (): void {
    $message = InboundMessage::fromArray(payloadFixture('inbound-auto-responder'));

    // It still cites the message it answers, so it belongs to the ticket — it
    // just must not trigger another automatic answer.
    expect($message->parentMessageIds())->toHaveCount(1)
        ->and($message->parentMessageIds()[0])->toContain('@tickets.graystack.one');
});

it('exposes the assigned message id on the outbound reception event', function (): void {
    $data = payloadFixture('outbound-reception-with-message-id');

    // Ahasend replaces any Message-ID we set, so this event is the only place
    // the assigned one can be learned — and it is what a later reply cites.
    expect($data['message_id_header'])->toStartWith('<019ffc12-')
        ->and($data['message_id_header'])->toContain('@tickets.graystack.one');
});

it('matches a bounce to its outbound mail through the same message id', function (): void {
    $bounce = new MailBounced(
        messageId: payloadFixture('webhook-message-bounced')['id'],
        recipient: payloadFixture('webhook-message-bounced')['recipient'],
        bounceType: payloadFixture('webhook-message-bounced')['bounce_type'] ?? null,
        payload: payloadFixture('webhook-message-bounced'),
    );

    // One anchor for both directions: a reply cites this id in In-Reply-To, a
    // bounce reports it here.
    expect($bounce->outboundMessageId())->toContain('@tickets.graystack.one')
        ->and($bounce->recipient)->toStartWith('kein-postfach@')
        // Ahasend states no reason on the bounce itself.
        ->and($bounce->bounceType)->toBeNull();
});

it('gets the bounce reason from the suppression event instead', function (): void {
    $data = payloadFixture('webhook-suppression-created');

    // This is where "why" lives, and what separates a hard bounce from a soft
    // one for an application that has to decide whether to stop sending.
    expect($data['reason'])->toBe('Invalid Recipient')
        ->and($data['recipient'])->toStartWith('kein-postfach@')
        ->and($data['expires_at'])->not->toBeEmpty();
});
