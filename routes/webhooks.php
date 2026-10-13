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
| Registered automatically by AhasendServiceProvider; every path comes from
| config. They are deliberately separate endpoints, because each is verified
| against a different secret:
|
| - ahasend/webhook      event webhooks, account-wide secret from config
| - ahasend/inboundmail/{channel}
|                        one endpoint per receiving domain, registered in
|                        InboundChannels; secret per channel, rate limited
|                        per channel and caller
| - ahasend/inboundmail  routes managed in the dashboard, secret per domain
|                        from config (deprecated in favour of channels)
| - ahasend/inbound/{id} routes provisioned per customer domain, secret stored
|                        with the route because it is only issued at creation
|
| All three are public, so they carry a rate limit — disable it by setting
| AHASEND_INBOUND_THROTTLE empty.
|
*/

$staticPath = trim((string) config('ahasend.inbound.static_path', 'ahasend/inboundmail'), '/');

// Its own limiter rather than the shared one: a flood on one channel must not
// throttle another, see AhasendServiceProvider::registerChannelLimiter().
Route::post($staticPath . '/{channel}', [InboundRouteController::class, 'handleChannel'])
    ->where('channel', '[a-z0-9-]+')
    ->middleware('throttle:ahasend-inbound-channel')
    ->name('ahasend.inbound.channel');

$throttle   = trim((string) config('ahasend.inbound.throttle', ''));
$middleware = $throttle === '' ? [] : ["throttle:{$throttle}"];

Route::middleware($middleware)->group(function (): void {
    Route::post(
        config('ahasend.webhook.path', 'ahasend/webhook'),
        [WebhookController::class, 'handle'],
    )->name('ahasend.webhook');

    Route::post(
        config('ahasend.inbound.static_path', 'ahasend/inboundmail'),
        [InboundRouteController::class, 'handleStatic'],
    )->name('ahasend.inbound.static');

    Route::post(
        trim((string) config('ahasend.inbound.path', 'ahasend/inbound'), '/') . '/{route}',
        [InboundRouteController::class, 'handle'],
    )->name('ahasend.inbound');
});
