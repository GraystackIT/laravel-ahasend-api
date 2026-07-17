<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;

it('sends the API key as an Authorization Bearer header', function (): void {
    $connector = app(AhasendConnector::class);

    $reflection = new ReflectionMethod($connector, 'defaultHeaders');
    $reflection->setAccessible(true);
    $headers = $reflection->invoke($connector);

    expect($headers)->toHaveKey('Authorization')
        ->and($headers['Authorization'])->toStartWith('Bearer ')
        ->and($headers)->not->toHaveKey('X-Api-Key');
});

it('resolves the base URL scoped to the configured account', function (): void {
    config(['ahasend.account_id' => 'acc-123']);

    $connector = app(AhasendConnector::class);

    expect($connector->resolveBaseUrl())->toEndWith('/accounts/acc-123');
});
