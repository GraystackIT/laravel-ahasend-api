<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Mail;

use GraystackIT\Ahasend\AhasendService;
use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class AhaSendTransport extends AbstractTransport
{
    public function __construct(
        private readonly AhasendService $service,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    /**
     * @throws TransportException
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            $this->service->send(new EmailMessage(
                fromEmail:   $this->resolveFromEmail($email),
                fromName:    $this->resolveFromName($email),
                to:          $this->mapAddresses($email->getTo()),
                subject:     $email->getSubject() ?? '',
                htmlContent: $email->getHtmlBody() ?: null,
                textContent: $email->getTextBody() ?: null,
                cc:          $this->mapAddresses($email->getCc()),
                bcc:         $this->mapAddresses($email->getBcc()),
                attachments: $this->extractAttachments($email),
            ));
        } catch (AhasendException $e) {
            throw new TransportException(
                'AhaSend API error: ' . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    public function __toString(): string
    {
        return 'ahasend://api';
    }

    private function resolveFromEmail(Email $email): string
    {
        $from = $email->getFrom();

        return isset($from[0]) ? $from[0]->getAddress() : (string) config('ahasend.from.address');
    }

    private function resolveFromName(Email $email): string
    {
        $from = $email->getFrom();

        return isset($from[0]) ? $from[0]->getName() : (string) config('ahasend.from.name');
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function mapAddresses(array $addresses): array
    {
        return array_values(array_map(function (Address $address): array {
            $entry = ['email' => $address->getAddress()];

            if ($address->getName() !== '') {
                $entry['name'] = $address->getName();
            }

            return $entry;
        }, $addresses));
    }

    /**
     * @return array<int, array{name: string, content: string, mime_type: string}>
     */
    private function extractAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $attachments[] = [
                'name'      => $attachment->getFilename() ?? 'attachment',
                'content'   => base64_encode($attachment->getBody()),
                'mime_type' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
            ];
        }

        return $attachments;
    }
}
