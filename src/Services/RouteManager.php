<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Events\InboundRouteProvisioned;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Models\AhasendDomain;
use GraystackIT\Ahasend\Models\AhasendRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provisions and retires the inbound route that makes a domain able to receive mail.
 *
 * Ahasend routes are account-level objects matched by a recipient pattern, and
 * the owning domain is derived from that pattern — so each domain that should
 * receive mail needs its own route, created only once the domain is verified.
 */
class RouteManager
{
    public function __construct(private readonly RouteService $routes) {}

    /**
     * Ensure a catch-all inbound route exists for the given domain.
     *
     * Idempotent: an existing local route for the domain is returned untouched.
     *
     * @throws AhasendException
     */
    public function provisionFor(AhasendDomain $domain): AhasendRoute
    {
        $existing = $domain->routes()->first();

        if ($existing instanceof AhasendRoute) {
            return $existing;
        }

        $publicId = (string) Str::ulid();
        $defaults = (array) config('ahasend.inbound.defaults', []);

        $data = $this->routes->create(
            name:               "Inbound {$domain->domain}",
            url:                $this->endpointUrl($publicId),
            recipient:          "*@{$domain->domain}",
            includeAttachments: (bool) ($defaults['attachments'] ?? true),
            includeHeaders:     (bool) ($defaults['headers'] ?? true),
            groupByMessageId:   (bool) ($defaults['group_by_message_id'] ?? true),
            stripReplies:       (bool) ($defaults['strip_replies'] ?? true),
            enabled:            true,
            idempotencyKey:     $publicId,
        );

        if ($data->secret === null) {
            Log::warning('Ahasend: route created without a secret — inbound payloads cannot be verified', [
                'route_id' => $data->id,
                'domain'   => $domain->domain,
            ]);
        }

        $route = (new AhasendRoute)
            ->fillFromApi($data)
            ->fill([
                'public_id'         => $publicId,
                'ahasend_domain_id' => $domain->getKey(),
            ]);

        $route->save();

        Log::info('Ahasend: inbound route provisioned', [
            'domain'    => $domain->domain,
            'recipient' => $route->recipient,
        ]);

        InboundRouteProvisioned::dispatch($route, $domain);

        return $route;
    }

    /**
     * Remove every route belonging to a domain, remotely and locally.
     *
     * A remote failure is logged but does not stop the local cleanup — an
     * orphaned remote route is recoverable, a stale local row is not.
     */
    public function retireFor(AhasendDomain $domain): void
    {
        foreach ($domain->routes()->get() as $route) {
            $this->retire($route);
        }
    }

    /**
     * Remove a single route, remotely and locally.
     */
    public function retire(AhasendRoute $route): void
    {
        try {
            $this->routes->delete($route->ahasend_route_id);
        } catch (AhasendException $e) {
            Log::warning('Ahasend: failed to delete inbound route remotely, removing locally anyway', [
                'route_id' => $route->ahasend_route_id,
                'error'    => $e->getMessage(),
            ]);
        }

        $route->delete();
    }

    /**
     * The public URL Ahasend posts inbound mail to for the given route.
     *
     * @throws AhasendException when no publicly reachable base URL is configured
     */
    public function endpointUrl(string $publicId): string
    {
        $baseUrl = (string) (config('ahasend.inbound.base_url') ?? config('app.url') ?? '');
        $path    = (string) config('ahasend.inbound.path', 'ahasend/inbound');

        if (trim($baseUrl) === '') {
            throw AhasendException::make(
                'No inbound base URL configured. Set AHASEND_INBOUND_BASE_URL (or APP_URL) to the '
                . 'publicly reachable URL of this application — Ahasend has to be able to POST to it.'
            );
        }

        return rtrim($baseUrl, '/') . '/' . trim($path, '/') . '/' . $publicId;
    }
}
