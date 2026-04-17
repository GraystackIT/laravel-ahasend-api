<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Tests;

use GraystackIT\Ahasend\AhasendServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register the package service provider for all tests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AhasendServiceProvider::class];
    }

    /**
     * Define environment configuration used across all tests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ahasend.api_key', 'test-api-key');
        $app['config']->set('ahasend.account_id', 'test-account-id');
        $app['config']->set('ahasend.base_url', 'https://api.ahasend.com/v2');
        $app['config']->set('ahasend.from.address', 'from@example.com');
        $app['config']->set('ahasend.from.name', 'Test Sender');
        $app['config']->set('ahasend.store_logs', false);
        $app['config']->set('ahasend.storage_driver', 'log');
        $app['config']->set('ahasend.webhook.secret', null);
        $app['config']->set('ahasend.retry.times', 1);
        $app['config']->set('ahasend.retry.delay', 0);
    }
}
