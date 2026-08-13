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
        // Domains without an owner are the application's own: their routes are
        // managed in the dashboard and verified against a configured secret.
        // Provisioning one here would give the domain a second route, and every
        // mail would arrive twice.
        if ($domain->owner_type === null) {
            throw AhasendException::make(
                "Refusing to provision a route for the shared domain {$domain->domain}. "
                . 'Domains without an owner are managed in the Ahasend dashboard.'
            );
        }

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
            groupByMessageId:   (bool) ($defaults['group_by_message_id'] ?? false),
            stripReplies:       (bool) ($defaults['strip_replies'] ?? true),
            enabled:            true,
            // Keyed on the domain, not on this call: a retry or a concurrent
            // "check DNS" must collapse into the same remote route instead of
            // creating a second one.
            idempotencyKey:     "route:{$domain->getKey()}",
        );

        if ($data->secret === null || $data->secret === '') {
            // Without a secret the endpoint could not verify anything, and an
            // unverifying public endpoint is worse than no route at all.
            $this->deleteRemote($data->id);

            throw AhasendException::make(
                "Ahasend returned no signing secret for the route on {$domain->domain}. "
                . 'The route was removed again; retry the DNS check to provision it.'
            );
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
        $this->deleteRemote($route->ahasend_route_id);

        $route->delete();
    }

    /**
     * Delete a route in Ahasend, tolerating failure.
     *
     * A remote failure is logged but never stops local cleanup: an orphaned
     * remote route is recoverable, a stale local row is not.
     */
    private function deleteRemote(string $routeId): void
    {
        try {
            $this->routes->delete($routeId);
        } catch (AhasendException $e) {
            Log::warning('Ahasend: failed to delete inbound route remotely', [
                'route_id' => $routeId,
                'error'    => $e->getMessage(),
            ]);
        }
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
