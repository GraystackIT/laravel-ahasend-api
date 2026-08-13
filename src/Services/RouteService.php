<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Route;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Routes\CreateRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\DeleteRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\GetRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\ListRoutesRequest;
use GraystackIT\Ahasend\Requests\Routes\UpdateRouteRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

/**
 * Thin wrapper around the Ahasend Routes API (inbound message routing).
 */
class RouteService
{
    public function __construct(private readonly AhasendConnector $connector) {}

    /**
     * Create an inbound route.
     *
     * The returned {@see Route::$secret} is the only copy that will ever exist —
     * persist it before doing anything else.
     *
     * @throws AhasendException
     */
    public function create(
        string $name,
        string $url,
        string $recipient,
        bool $includeAttachments = false,
        bool $includeHeaders = false,
        bool $groupByMessageId = false,
        bool $stripReplies = false,
        bool $enabled = true,
        ?string $idempotencyKey = null,
    ): Route {
        Log::info('Ahasend: creating inbound route', ['recipient' => $recipient, 'url' => $url]);

        $request = new CreateRouteRequest(
            $name,
            $url,
            $recipient,
            $includeAttachments,
            $includeHeaders,
            $groupByMessageId,
            $stripReplies,
            $enabled,
        );

        if ($idempotencyKey !== null) {
            $request->withIdempotencyKey($idempotencyKey);
        }

        return $this->send($request, 'create route', ['recipient' => $recipient]);
    }

    /**
     * Fetch a single route.
     *
     * @throws AhasendException
     */
    public function get(string $routeId): Route
    {
        return $this->send(new GetRouteRequest($routeId), 'get route', ['route_id' => $routeId]);
    }

    /**
     * List the account's routes.
     *
     * @return array{data: list<Route>, meta: array<string, mixed>}
     *
     * @throws AhasendException
     */
    public function list(?int $limit = null, ?string $after = null, ?string $before = null): array
    {
        try {
            $response = $this->connector->send(new ListRoutesRequest($limit, $after, $before));
            $body     = $response->json();

            /** @var list<array<string, mixed>> $items */
            $items = $body['data'] ?? [];

            return [
                'data' => array_values(array_map(
                    static fn (array $item): Route => Route::fromArray($item),
                    $items,
                )),
                'meta' => $body['meta'] ?? [],
            ];
        } catch (RequestException $e) {
            throw $this->fail($e, 'list routes', []);
        }
    }

    /**
     * Update a route's mutable attributes.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AhasendException
     */
    public function update(string $routeId, array $attributes): Route
    {
        return $this->send(
            new UpdateRouteRequest($routeId, $attributes),
            'update route',
            ['route_id' => $routeId],
        );
    }

    /**
     * Delete a route.
     *
     * @throws AhasendException
     */
    public function delete(string $routeId): bool
    {
        Log::info('Ahasend: deleting inbound route', ['route_id' => $routeId]);

        try {
            return $this->connector->send(new DeleteRouteRequest($routeId))->successful();
        } catch (RequestException $e) {
            throw $this->fail($e, 'delete route', ['route_id' => $routeId]);
        }
    }

    /**
     * Send a request that returns a single route object.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws AhasendException
     */
    private function send(object $request, string $action, array $context): Route
    {
        try {
            /** @var \Saloon\Http\Request $request */
            return Route::fromArray($this->connector->send($request)->json());
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
