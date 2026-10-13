<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Console\ExpireDomainsCommand;
use GraystackIT\Ahasend\Console\ImportRouteCommand;
use GraystackIT\Ahasend\Console\PollDomainsCommand;
use GraystackIT\Ahasend\Console\PruneInboundHandoffsCommand;
use GraystackIT\Ahasend\Contracts\InboundScreen;
use GraystackIT\Ahasend\Mail\AhaSendTransport;
use GraystackIT\Ahasend\Services\DomainManager;
use GraystackIT\Ahasend\Services\DomainService;
use GraystackIT\Ahasend\Services\MessageService;
use GraystackIT\Ahasend\Services\ReportService;
use GraystackIT\Ahasend\Services\RouteManager;
use GraystackIT\Ahasend\Services\RouteService;
use GraystackIT\Ahasend\Services\SmtpCredentialService;
use GraystackIT\Ahasend\Services\SuppressionService;
use GraystackIT\Ahasend\Support\AcceptAllScreen;
use GraystackIT\Ahasend\Support\InboundChannels;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AhasendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ahasend.php',
            'ahasend',
        );

        // Bind the Saloon connector as a singleton.
        $this->app->singleton(AhasendConnector::class, function (): AhasendConnector {
            $apiKey    = config('ahasend.api_key');
            $accountId = config('ahasend.account_id');
            $baseUrl   = config('ahasend.base_url', 'https://api.ahasend.com/v2');

            if (empty($apiKey)) {
                throw new \RuntimeException(
                    'Ahasend API key is not set. Define AHASEND_API_KEY in your .env file.'
                );
            }

            if (empty($accountId)) {
                throw new \RuntimeException(
                    'Ahasend account ID is not set. Define AHASEND_ACCOUNT_ID in your .env file.'
                );
            }

            return new AhasendConnector((string) $apiKey, (string) $accountId, (string) $baseUrl);
        });

        // Bind the service — depends on the connector singleton above.
        $this->app->singleton(AhasendService::class, function (): AhasendService {
            return new AhasendService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(MessageService::class, function (): MessageService {
            return new MessageService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(SmtpCredentialService::class, function (): SmtpCredentialService {
            return new SmtpCredentialService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(SuppressionService::class, function (): SuppressionService {
            return new SuppressionService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(ReportService::class, function (): ReportService {
            return new ReportService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(DomainService::class, function (): DomainService {
            return new DomainService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(RouteService::class, function (): RouteService {
            return new RouteService($this->app->make(AhasendConnector::class));
        });

        $this->app->singleton(RouteManager::class, function (): RouteManager {
            return new RouteManager($this->app->make(RouteService::class));
        });

        $this->app->singleton(InboundChannels::class);

        // An application screens deliveries by binding its own implementation.
        $this->app->bindIf(InboundScreen::class, AcceptAllScreen::class);

        $this->app->singleton(DomainManager::class, function (): DomainManager {
            return new DomainManager(
                $this->app->make(DomainService::class),
                $this->app->make(RouteManager::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PollDomainsCommand::class,
                ExpireDomainsCommand::class,
                ImportRouteCommand::class,
                PruneInboundHandoffsCommand::class,
            ]);

            // Publish config.
            $this->publishes([
                __DIR__ . '/../config/ahasend.php' => config_path('ahasend.php'),
            ], 'ahasend-config');

            // Publish migrations.
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'ahasend-migrations');
        }

        // Load migrations so they can run without publishing.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerChannelLimiter();

        // Register webhook route (excluded from CSRF middleware by default).
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        // Register the 'ahasend' Laravel mail transport driver.
        Mail::extend('ahasend', function () {
            return new AhaSendTransport($this->app->make(AhasendService::class));
        });
    }

    /**
     * The rate limit of the named channel endpoints.
     *
     * Keyed by channel and caller together: by channel alone, anybody posting
     * junk to a channel would use up the budget Ahasend's own deliveries need.
     */
    private function registerChannelLimiter(): void
    {
        RateLimiter::for('ahasend-inbound-channel', function (Request $request): Limit {
            $perMinute = (int) config('ahasend.inbound.channel_throttle', 120);

            if ($perMinute <= 0) {
                return Limit::none();
            }

            return Limit::perMinute($perMinute)->by(
                'ahasend-inbound:' . (string) $request->route('channel') . '|' . (string) $request->ip(),
            );
        });
    }
}
