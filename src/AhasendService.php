<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Events\MailSent;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Models\AhasendMessage;
use GraystackIT\Ahasend\Requests\SendEmailRequest;
use GraystackIT\Ahasend\Requests\SendEmailWithAttachmentsRequest;
use GraystackIT\Ahasend\Requests\SendHtmlEmailRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\RequestException;

class AhasendService
{
    public function __construct(
        private readonly AhasendConnector $connector,
    ) {}

    /**
     * Send an email message via Ahasend.
     *
     * Automatically selects the appropriate request type based on message content:
     * - Attachments present  → SendEmailWithAttachmentsRequest
     * - HTML content present → SendHtmlEmailRequest
     * - Otherwise           → SendEmailRequest (plain text)
     *
     * @throws AhasendException
     */
    public function send(EmailMessage $message): string
    {
        // Ensure a message_id exists so webhook events can be correlated.
        if ($message->messageId === null) {
            $message = new EmailMessage(
                fromEmail:   $message->fromEmail,
                fromName:    $message->fromName,
                to:          $message->to,
                subject:     $message->subject,
                htmlContent: $message->htmlContent,
                textContent: $message->textContent,
                cc:          $message->cc,
                bcc:         $message->bcc,
                attachments: $message->attachments,
                messageId:   (string) Str::uuid(),
            );
        }

        Log::info('Ahasend: sending email', [
            'to'         => $message->to,
            'subject'    => $message->subject,
            'message_id' => $message->messageId,
        ]);

        try {
            $request = $this->resolveRequest($message);

            $retryTimes = (int) config('ahasend.retry.times', 3);
            $retryDelay = (int) config('ahasend.retry.delay', 500);

            $response = $this->connector
                ->sendAndRetry($request, $retryTimes, $retryDelay);

            $responseData    = $response->json();
            $ahasendMsgId    = $responseData['message_id'] ?? $message->messageId;

            Log::info('Ahasend: email sent successfully', [
                'message_id'         => $message->messageId,
                'ahasend_message_id' => $ahasendMsgId,
            ]);

            $this->storeOutgoing($message, $ahasendMsgId);

            MailSent::dispatch($message, $ahasendMsgId);

            return (string) $ahasendMsgId;
        } catch (RequestException $e) {
            Log::error('Ahasend: API request failed', [
                'message_id' => $message->messageId,
                'error'      => $e->getMessage(),
                'status'     => $e->getResponse()->status(),
            ]);

            throw AhasendException::fromRequestException($e);
        } catch (\Throwable $e) {
            Log::error('Ahasend: unexpected error', [
                'message_id' => $message->messageId,
                'error'      => $e->getMessage(),
            ]);

            throw AhasendException::make($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Convenience method: send a plain-text email.
     *
     * @param  array<int, array{email: string, name?: string}>  $to
     * @throws AhasendException
     */
    public function sendText(
        array $to,
        string $subject,
        string $textContent,
        string $fromEmail = '',
        string $fromName = '',
        array $cc = [],
        array $bcc = [],
    ): string {
        return $this->send(new EmailMessage(
            fromEmail:   $fromEmail ?: (string) config('ahasend.from.address'),
            fromName:    $fromName ?: (string) config('ahasend.from.name'),
            to:          $to,
            subject:     $subject,
            textContent: $textContent,
            cc:          $cc,
            bcc:         $bcc,
        ));
    }

    /**
     * Convenience method: send an HTML email with optional plain-text fallback.
     *
     * @param  array<int, array{email: string, name?: string}>  $to
     * @throws AhasendException
     */
    public function sendHtml(
        array $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        string $fromEmail = '',
        string $fromName = '',
        array $cc = [],
        array $bcc = [],
    ): string {
        return $this->send(new EmailMessage(
            fromEmail:   $fromEmail ?: (string) config('ahasend.from.address'),
            fromName:    $fromName ?: (string) config('ahasend.from.name'),
            to:          $to,
            subject:     $subject,
            htmlContent: $htmlContent,
            textContent: $textContent,
            cc:          $cc,
            bcc:         $bcc,
        ));
    }

    /**
     * Convenience method: send an email with file attachments.
     *
     * @param  array<int, array{email: string, name?: string}>  $to
     * @param  array<int, array{name: string, content: string, mime_type: string}>  $attachments
     * @throws AhasendException
     */
    public function sendWithAttachments(
        array $to,
        string $subject,
        array $attachments,
        ?string $htmlContent = null,
        ?string $textContent = null,
        string $fromEmail = '',
        string $fromName = '',
        array $cc = [],
        array $bcc = [],
    ): string {
        return $this->send(new EmailMessage(
            fromEmail:   $fromEmail ?: (string) config('ahasend.from.address'),
            fromName:    $fromName ?: (string) config('ahasend.from.name'),
            to:          $to,
            subject:     $subject,
            htmlContent: $htmlContent,
            textContent: $textContent,
            cc:          $cc,
            bcc:         $bcc,
            attachments: $attachments,
        ));
    }

    /**
     * Pick the correct Saloon request class based on message contents.
     */
    private function resolveRequest(EmailMessage $message): SendEmailRequest|SendHtmlEmailRequest|SendEmailWithAttachmentsRequest
    {
        if (! empty($message->attachments)) {
            return new SendEmailWithAttachmentsRequest($message);
        }

        if ($message->htmlContent !== null) {
            return new SendHtmlEmailRequest($message);
        }

        return new SendEmailRequest($message);
    }

    /**
     * Persist outgoing email details according to the configured storage driver.
     */
    private function storeOutgoing(EmailMessage $message, string $ahasendMsgId): void
    {
        if (! config('ahasend.store_logs', false)) {
            return;
        }

        $driver = config('ahasend.storage_driver', 'log');

        if ($driver === 'database') {
            AhasendMessage::create([
                'message_id' => $ahasendMsgId,
                'recipient'  => collect($message->to)->pluck('email')->implode(','),
                'subject'    => $message->subject,
                'status'     => 'sent',
                'payload'    => $message->toArray(),
            ]);

            return;
        }

        // Default: write to Laravel log.
        Log::channel(config('logging.default'))->info('Ahasend: outgoing email stored', [
            'message_id' => $ahasendMsgId,
            'to'         => $message->to,
            'subject'    => $message->subject,
        ]);
    }
}
