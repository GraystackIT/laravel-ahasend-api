<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Requests\SendConversationalEmailRequest;
use GraystackIT\Ahasend\Requests\SendEmailWithAttachmentsRequest;

/**
 * The attachment part of a send payload, which is where an inline image is
 * either made possible or quietly lost.
 */
function messageWithAttachment(array $attachment, array $cc = []): EmailMessage
{
    return new EmailMessage(
        fromEmail:   'from@example.com',
        fromName:    'Sender',
        to:          [['email' => 'to@example.com']],
        subject:     'Subject',
        htmlContent: '<p>Hello</p>',
        cc:          $cc,
        attachments: [$attachment],
    );
}

it('builds an ordinary attachment without inline fields', function (): void {
    $request = new SendEmailWithAttachmentsRequest(messageWithAttachment([
        'name'      => 'invoice.pdf',
        'content'   => 'raw pdf bytes!',
        'mime_type' => 'application/pdf',
    ]));

    $attachment = $request->body()->all()['attachments'][0];

    expect($attachment)->toBe([
        'file_name'    => 'invoice.pdf',
        'data'         => base64_encode('raw pdf bytes!'),
        'content_type' => 'application/pdf',
        'base64'       => true,
    ]);
});

it('marks an attachment carrying a content id as inline', function (): void {
    $request = new SendEmailWithAttachmentsRequest(messageWithAttachment([
        'name'       => 'logo.png',
        'content'    => 'raw png bytes!',
        'mime_type'  => 'image/png',
        'content_id' => 'logo@example.com',
    ]));

    $attachment = $request->body()->all()['attachments'][0];

    expect($attachment['content_id'])->toBe('<logo@example.com>')
        ->and($attachment['content_disposition'])->toBe('inline');
});

it('wraps a content id the caller left unbracketed', function (): void {
    // Without the brackets the API delivers the file as a download, and the
    // `cid:` in the body finds nothing — so a bare id is corrected, not passed
    // on as given.
    $request = new SendEmailWithAttachmentsRequest(messageWithAttachment([
        'name'       => 'logo.png',
        'content'    => 'raw png bytes!',
        'content_id' => '<logo@example.com>',
    ]));

    expect($request->body()->all()['attachments'][0]['content_id'])->toBe('<logo@example.com>');
});

it('ignores an empty content id', function (): void {
    $request = new SendEmailWithAttachmentsRequest(messageWithAttachment([
        'name'       => 'logo.png',
        'content'    => 'raw png bytes!',
        'content_id' => '',
    ]));

    expect($request->body()->all()['attachments'][0])->not->toHaveKey('content_id');
});

it('carries inline attachments on the conversational endpoint too', function (): void {
    $request = new SendConversationalEmailRequest(messageWithAttachment([
        'name'       => 'logo.png',
        'content'    => 'raw png bytes!',
        'content_id' => 'logo@example.com',
    ], [['email' => 'cc@example.com']]));

    $attachment = $request->body()->all()['attachments'][0];

    expect($attachment['content_id'])->toBe('<logo@example.com>')
        ->and($attachment['content_disposition'])->toBe('inline');
});

it('keeps a disposition the caller states', function (): void {
    $request = new SendEmailWithAttachmentsRequest(messageWithAttachment([
        'name'                => 'logo.png',
        'content'             => 'raw png bytes!',
        'content_id'          => 'logo@example.com',
        'content_disposition' => 'attachment',
    ]));

    expect($request->body()->all()['attachments'][0]['content_disposition'])->toBe('attachment');
});
