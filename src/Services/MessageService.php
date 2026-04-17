<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Message;
use GraystackIT\Ahasend\Enums\MessageStatus;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Messages\CancelScheduledMessageRequest;
use GraystackIT\Ahasend\Requests\Messages\GetMessageRequest;
use GraystackIT\Ahasend\Requests\Messages\ListMessagesRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

class MessageService
{
    public function __construct(private readonly AhasendConnector $connector) {}

    /**
     * Retrieve a single message by ID.
     *
     * @throws AhasendException
     */
    public function get(string $messageId): Message
    {
        Log::info('Ahasend: fetching message', ['message_id' => $messageId]);

        try {
            $response = $this->connector->send(new GetMessageRequest($messageId));

            return Message::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to get message', [
                'message_id' => $messageId,
                'status'     => $e->getResponse()->status(),
                'error'      => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * List messages with optional filters.
     *
     * @return array{data: Message[], meta: array<string, mixed>}
     * @throws AhasendException
     */
    public function list(
        int            $page = 1,
        int            $perPage = 25,
        ?MessageStatus $status = null,
        ?string        $from = null,
        ?string        $to = null,
        ?string        $email = null,
    ): array {
        Log::info('Ahasend: listing messages', compact('page', 'perPage', 'status'));

        try {
            $response = $this->connector->send(
                new ListMessagesRequest($page, $perPage, $status, $from, $to, $email),
            );

            $body = $response->json();

            return [
                'data' => array_map(
                    static fn (array $item): Message => Message::fromArray($item),
                    $body['data'] ?? [],
                ),
                'meta' => $body['meta'] ?? [],
            ];
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to list messages', [
                'error'  => $e->getMessage(),
                'status' => $e->getResponse()->status(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Cancel a scheduled message.
     *
     * @throws AhasendException
     */
    public function cancel(string $messageId): bool
    {
        Log::info('Ahasend: cancelling scheduled message', ['message_id' => $messageId]);

        try {
            $response = $this->connector->send(new CancelScheduledMessageRequest($messageId));

            Log::info('Ahasend: scheduled message cancelled', ['message_id' => $messageId]);

            return $response->successful();
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to cancel message', [
                'message_id' => $messageId,
                'status'     => $e->getResponse()->status(),
                'error'      => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }
}
