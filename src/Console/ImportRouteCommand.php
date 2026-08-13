<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Console;

use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Models\AhasendDomain;
use GraystackIT\Ahasend\Models\AhasendRoute;
use GraystackIT\Ahasend\Services\RouteManager;
use GraystackIT\Ahasend\Services\RouteService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Adopts an inbound route that was created in the Ahasend dashboard.
 *
 * Routes this package provisions store their secret automatically. A route
 * created by hand cannot do that — Ahasend returns the signing secret only at
 * creation, and the API never hands it out again — so the secret has to be
 * pasted in from the dashboard once.
 *
 * The command also repoints the route at this application's inbound endpoint,
 * because that URL carries the route id the endpoint looks the secret up by.
 */
class ImportRouteCommand extends Command
{
    protected $signature = 'ahasend:routes:import
        {route : The route id from the Ahasend dashboard}
        {--secret= : The route signing secret (prompted for when omitted)}
        {--keep-url : Leave the route URL as it is instead of repointing it}';

    protected $description = 'Adopt an inbound route created in the Ahasend dashboard';

    public function handle(RouteService $routes, RouteManager $manager): int
    {
        $routeId = (string) $this->argument('route');

        try {
            $data = $routes->get($routeId);
        } catch (AhasendException $e) {
            $this->components->error("Could not fetch route {$routeId}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $existing = AhasendRoute::query()->where('ahasend_route_id', $data->id)->first();

        if ($existing instanceof AhasendRoute) {
            $this->components->warn("Route {$data->id} is already known locally as {$existing->public_id}.");

            return self::SUCCESS;
        }

        $secret = (string) ($this->option('secret') ?? $this->secretFromPrompt());

        if (trim($secret) === '') {
            $this->components->error(
                'A signing secret is required. Without it inbound payloads cannot be verified, '
                . 'and the endpoint would accept anything.'
            );

            return self::FAILURE;
        }

        $publicId = (string) Str::ulid();

        $route = (new AhasendRoute)
            ->fillFromApi($data)
            ->fill([
                'public_id'         => $publicId,
                'secret'            => $secret,
                'ahasend_domain_id' => $this->matchingDomainId($data->domain()),
            ]);

        $route->save();

        $this->components->info("Imported route {$data->id} for {$data->recipient}.");

        if ($this->option('keep-url')) {
            $this->components->warn(
                'Route URL left unchanged. Inbound mail only reaches this application once it points at '
                . $manager->endpointUrl($publicId)
            );

            return self::SUCCESS;
        }

        $url = $manager->endpointUrl($publicId);

        try {
            $routes->update($data->id, ['url' => $url]);
        } catch (AhasendException $e) {
            $this->components->error("Imported, but the route URL could not be updated: {$e->getMessage()}");
            $this->components->warn("Set it to {$url} in the dashboard.");

            return self::FAILURE;
        }

        $this->components->info("Route now delivers to {$url}");

        return self::SUCCESS;
    }

    /**
     * Ask for the secret without echoing it to the terminal.
     */
    private function secretFromPrompt(): string
    {
        return (string) $this->secret('Route signing secret (from the dashboard)');
    }

    /**
     * The local domain record this route belongs to, when we track it.
     */
    private function matchingDomainId(?string $domain): ?int
    {
        if ($domain === null) {
            return null;
        }

        return AhasendDomain::query()->where('domain', $domain)->value('id');
    }
}
