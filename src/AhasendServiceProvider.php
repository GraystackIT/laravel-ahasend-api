<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
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
            $apiKey  = config('ahasend.api_key');
            $baseUrl = config('ahasend.base_url', 'https://api.ahasend.com/v1');

            if (empty($apiKey)) {
                throw new \RuntimeException(
                    'Ahasend API key is not set. Define AHASEND_API_KEY in your .env file.'
                );
            }

            return new AhasendConnector((string) $apiKey, (string) $baseUrl);
        });

        // Bind the service — depends on the connector singleton above.
        $this->app->singleton(AhasendService::class, function (): AhasendService {
            return new AhasendService($this->app->make(AhasendConnector::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
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

        // Register webhook route (excluded from CSRF middleware by default).
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
    }
}
