<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Domain;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Domains\CheckDomainDnsRequest;
use GraystackIT\Ahasend\Requests\Domains\CreateDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\DeleteDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\GetDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\ListDomainsRequest;
use GraystackIT\Ahasend\Requests\Domains\UpdateDomainRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

/**
 * Thin wrapper around the Ahasend Domains API.
 *
 * Stateless by design — persistence and the domain lifecycle live in
 * {@see \GraystackIT\Ahasend\Services\DomainManager}.
 */
class DomainService
{
    public function __construct(private readonly AhasendConnector $connector) {}

    /**
     * Register a domain on the account.
     *
     * @param  string|null  $idempotencyKey  Reuse across retries of the same logical create.
     *
     * @throws AhasendException
     */
    public function create(string $domain, ?string $idempotencyKey = null): Domain
    {
        Log::info('Ahasend: creating domain', ['domain' => $domain]);

        $request = new CreateDomainRequest($domain);

        if ($idempotencyKey !== null) {
            $request->withIdempotencyKey($idempotencyKey);
        }

        return $this->send($request, 'create domain', ['domain' => $domain]);
    }

    /**
     * Fetch a single domain including its DNS record state.
     *
     * @throws AhasendException
     */
    public function get(string $domain): Domain
    {
        return $this->send(new GetDomainRequest($domain), 'get domain', ['domain' => $domain]);
    }

    /**
     * List the account's domains.
     *
     * @return array{data: list<Domain>, meta: array<string, mixed>}
     *
     * @throws AhasendException
     */
    public function list(?int $limit = null, ?string $after = null, ?string $before = null): array
    {
        try {
            $response = $this->connector->send(new ListDomainsRequest($limit, $after, $before));
            $body     = $response->json();

            /** @var list<array<string, mixed>> $items */
            $items = $body['data'] ?? [];

            return [
                'data' => array_values(array_map(
                    static fn (array $item): Domain => Domain::fromArray($item),
                    $items,
                )),
                'meta' => $body['meta'] ?? [],
            ];
        } catch (RequestException $e) {
            throw $this->fail($e, 'list domains', []);
        }
    }

    /**
     * Update a domain's mutable attributes.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AhasendException
     */
    public function update(string $domain, array $attributes): Domain
    {
        return $this->send(
            new UpdateDomainRequest($domain, $attributes),
            'update domain',
            ['domain' => $domain],
        );
    }

    /**
     * Trigger a DNS verification run and return the domain's resulting state.
     *
     * @throws AhasendException
     */
    public function checkDns(string $domain): Domain
    {
        Log::info('Ahasend: checking domain DNS', ['domain' => $domain]);

        return $this->send(new CheckDomainDnsRequest($domain), 'check domain DNS', ['domain' => $domain]);
    }

    /**
     * Remove a domain from the account.
     *
     * @throws AhasendException
     */
    public function delete(string $domain): bool
    {
        Log::info('Ahasend: deleting domain', ['domain' => $domain]);

        try {
            return $this->connector->send(new DeleteDomainRequest($domain))->successful();
        } catch (RequestException $e) {
            throw $this->fail($e, 'delete domain', ['domain' => $domain]);
        }
    }

    /**
     * Send a request that returns a single domain object.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws AhasendException
     */
    private function send(object $request, string $action, array $context): Domain
    {
        try {
            /** @var \Saloon\Http\Request $request */
            return Domain::fromArray($this->connector->send($request)->json());
        } catch (RequestException $e) {
            throw $this->fail($e, $action, $context);
        }
    }

    /**
     * Log and convert a failed API call.
     *
     * @param  array<string, mixed>  $context
     */
    private function fail(RequestException $e, string $action, array $context): AhasendException
    {
        Log::error("Ahasend: failed to {$action}", [
            ...$context,
            'status' => $e->getResponse()->status(),
            'error'  => $e->getMessage(),
        ]);

        return AhasendException::fromRequestException($e);
    }
}
