<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ahasend Webhook Route
|--------------------------------------------------------------------------
|
| This route is registered automatically by AhasendServiceProvider.
| The path is configurable via config('ahasend.webhook.path').
|
*/

Route::post(
    config('ahasend.webhook.path', 'ahasend/webhook'),
    [WebhookController::class, 'handle'],
)->name('ahasend.webhook');
