<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Traits;

use GraystackIT\Ahasend\Models\AhasendMessage;
use Illuminate\Support\Str;

/**
 * Add this trait to a Laravel Mailable to automatically:
 *  - Generate a unique message_id before the mail is built.
 *  - Store the outgoing mail details in the ahasend_messages table
 *    (when the database driver is enabled).
 *
 * Usage:
 *
 *   class OrderShipped extends Mailable
 *   {
 *       use TracksAhasendMail;
 *       ...
 *   }
 *
 * The message_id is exposed as a public property so webhook events can be
 * correlated back to this specific send.
 */
trait TracksAhasendMail
{
    /** Unique identifier for this send; attached to the message headers. */
    public string $ahasendMessageId;

    /**
     * Call from build() (or __construct) to prepare tracking.
     * Sets $ahasendMessageId and stores the outgoing record if logging is enabled.
     *
     * @param  string  $recipient  Primary recipient email address.
     */
    public function initAhasendTracking(string $recipient = ''): void
    {
        $this->ahasendMessageId = (string) Str::uuid();

        // Attach as a custom header so the MTA / transport can propagate it.
        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message): void {
            $message->getHeaders()->addTextHeader('X-Ahasend-Message-Id', $this->ahasendMessageId);
        });

        $this->storeOutgoingRecord($recipient);
    }

    /**
     * Persist an initial "queued" record in ahasend_messages when the
     * database driver is configured and store_logs is enabled.
     */
    private function storeOutgoingRecord(string $recipient): void
    {
        if (! config('ahasend.store_logs', false)) {
            return;
        }

        if (config('ahasend.storage_driver') !== 'database') {
            return;
        }

        AhasendMessage::create([
            'message_id' => $this->ahasendMessageId,
            'recipient'  => $recipient,
            'subject'    => $this->subject ?? class_basename(static::class),
            'status'     => 'queued',
            'payload'    => [],
        ]);
    }
}
