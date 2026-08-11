<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Http\Controllers\InboundRouteController;
use GraystackIT\Ahasend\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ahasend Webhook Routes
|--------------------------------------------------------------------------
|
| Registered automatically by AhasendServiceProvider; both paths come from
| config. They are deliberately separate endpoints: event webhooks are signed
| with the account-wide webhook secret, while every inbound route signs with
| its own secret and is therefore identified by the id in its path.
|
*/

Route::post(
    config('ahasend.webhook.path', 'ahasend/webhook'),
    [WebhookController::class, 'handle'],
)->name('ahasend.webhook');

Route::post(
    trim((string) config('ahasend.inbound.path', 'ahasend/inbound'), '/') . '/{route}',
    [InboundRouteController::class, 'handle'],
)->name('ahasend.inbound');
