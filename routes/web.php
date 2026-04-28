<?php

use Illuminate\Support\Facades\Route;
use ShadrackJm\Snippe\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Snippe Webhook Route
|--------------------------------------------------------------------------
| This route is registered automatically by the SnippeServiceProvider.
| The URI path is controlled by the 'snippe.webhook_path' config value,
| which defaults to 'snippe/webhook' (env: SNIPPE_WEBHOOK_PATH).
|
| IMPORTANT: Make sure to exclude this route from CSRF protection by
| adding the path to your App\Http\Middleware\VerifyCsrfToken $except array,
| or using Sanctum's stateful domains. In Laravel 11+, you can do this in
| bootstrap/app.php:
|
|   ->withMiddleware(function (Middleware $middleware) {
|       $middleware->validateCsrfTokens(except: [
|           'snippe/*',
|       ]);
|   })
|
*/

Route::post(config('snippe.webhook_path', 'snippe/webhook'), [WebhookController::class, 'handle'])
    ->name('snippe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
