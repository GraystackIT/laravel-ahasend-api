<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Suppression;
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
     * @param  string  $expiresAt  RFC3339 datetime (e.g. "2026-12-31T00:00:00Z")
     *
     * @throws AhasendException
     */
    public function create(
        string  $email,
        string  $expiresAt,
        ?string $reason = null,
        ?string $domain = null,
    ): Suppression {
        Log::info('Ahasend: creating suppression', ['email' => $email]);

        try {
            $response    = $this->connector->send(
                new CreateSuppressionRequest($email, $expiresAt, $reason, $domain),
            );
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
     * List suppressions with optional cursor-based pagination and filters.
     *
     * @return array{data: Suppression[], meta: array<string, mixed>}
     * @throws AhasendException
     */
    public function list(
        ?int    $limit = null,
        ?string $after = null,
        ?string $before = null,
        ?string $domain = null,
        ?string $email = null,
        ?string $fromTime = null,
        ?string $toTime = null,
    ): array {
        Log::info('Ahasend: listing suppressions');

        try {
            $response = $this->connector->send(
                new ListSuppressionsRequest($limit, $after, $before, $domain, $email, $fromTime, $toTime),
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
    public function delete(string $email, ?string $domain = null): bool
    {
        Log::info('Ahasend: deleting suppression', ['email' => $email]);

        try {
            $response = $this->connector->send(new DeleteSuppressionRequest($email, $domain));

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
     * Delete all suppressions, optionally scoped to a single sending domain.
     *
     * @throws AhasendException
     */
    public function deleteAll(?string $domain = null): bool
    {
        Log::info('Ahasend: deleting all suppressions');

        try {
            $response = $this->connector->send(new DeleteAllSuppressionsRequest($domain));

            Log::info('Ahasend: all suppressions deleted');

            return $response->successful();
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to delete all suppressions', [
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }
}
