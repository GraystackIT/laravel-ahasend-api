<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Suppression;
use GraystackIT\Ahasend\Enums\SuppressionType;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Suppressions\CreateSuppressionRequest;
use GraystackIT\Ahasend\Requests\Suppressions\DeleteAllSuppressionsRequest;
use GraystackIT\Ahasend\Requests\Suppressions\DeleteSuppressionRequest;
use GraystackIT\Ahasend\Requests\Suppressions\ListSuppressionsRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

class SuppressionService
{
    public function __construct(private readonly AhasendConnector $connector) {}

    /**
     * Add an email address to the suppression list.
     *
     * @throws AhasendException
     */
    public function create(
        string          $email,
        SuppressionType $type = SuppressionType::Manual,
        ?string         $reason = null,
    ): Suppression {
        Log::info('Ahasend: creating suppression', ['email' => $email, 'type' => $type->value]);

        try {
            $response    = $this->connector->send(new CreateSuppressionRequest($email, $type, $reason));
            $suppression = Suppression::fromArray($response->json());

            Log::info('Ahasend: suppression created', ['email' => $email]);

            return $suppression;
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to create suppression', [
                'email'  => $email,
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * List suppressions with optional filters.
     *
     * @return array{data: Suppression[], meta: array<string, mixed>}
     * @throws AhasendException
     */
    public function list(
        int              $page = 1,
        int              $perPage = 25,
        ?SuppressionType $type = null,
        ?string          $email = null,
    ): array {
        Log::info('Ahasend: listing suppressions', compact('page', 'perPage'));

        try {
            $response = $this->connector->send(
                new ListSuppressionsRequest($page, $perPage, $type, $email),
            );

            $body = $response->json();

            return [
                'data' => array_map(
                    static fn (array $item): Suppression => Suppression::fromArray($item),
                    $body['data'] ?? [],
                ),
                'meta' => $body['meta'] ?? [],
            ];
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to list suppressions', [
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Remove a specific email address from the suppression list.
     *
     * @throws AhasendException
     */
    public function delete(string $email): bool
    {
        Log::info('Ahasend: deleting suppression', ['email' => $email]);

        try {
            $response = $this->connector->send(new DeleteSuppressionRequest($email));

            Log::info('Ahasend: suppression deleted', ['email' => $email]);

            return $response->successful();
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to delete suppression', [
                'email'  => $email,
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Delete all suppressions, optionally filtered by type.
     *
     * @throws AhasendException
     */
    public function deleteAll(?SuppressionType $type = null): bool
    {
        Log::info('Ahasend: deleting all suppressions', ['type' => $type?->value ?? 'all']);

        try {
            $response = $this->connector->send(new DeleteAllSuppressionsRequest($type));

            Log::info('Ahasend: all suppressions deleted', ['type' => $type?->value ?? 'all']);

            return $response->successful();
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to delete all suppressions', [
                'type'   => $type?->value,
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }
}
