<?php

namespace ShadrackJm\Snippe;

use Illuminate\Support\ServiceProvider;
use ShadrackJm\Snippe\Http\Controllers\WebhookController;

class SnippeServiceProvider extends ServiceProvider
{
    /**
     * Register package services into the container.
     *
     * Binds SnippeClient as a singleton so it is only instantiated once
     * and all callers (including the Facade) share the same instance.
     */
    public function register(): void
    {
        // Merge our defaults with any published config so users only need
        // to define the keys they want to override.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/snippe.php',
            'snippe',
        );

        // Bind SnippeClient as a singleton
        $this->app->singleton(SnippeClient::class, function ($app) {
            $config = $app['config']['snippe'];

            return new SnippeClient(
                apiKey:     $config['api_key']     ?? '',
                baseUrl:    $config['base_url']    ?? 'https://api.snippe.sh/v1',
                timeout:    (int) ($config['timeout']  ?? 30),
                webhookUrl: $config['webhook_url'] ?? null,
            );
        });

        // Allow resolution by the short alias 'snippe' as well
        $this->app->alias(SnippeClient::class, 'snippe');
    }

    /**
     * Bootstrap package services.
     *
     * Registers publishable assets and the package's optional webhook route.
     */
    public function boot(): void
    {
        // ── Publishable Config ────────────────────────────────────────────────
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/snippe.php' => config_path('snippe.php'),
            ], 'snippe-config');
        }

        // ── Webhook Route ─────────────────────────────────────────────────────
        // The package registers a POST route at the path defined in config.
        // Developers can disable this by setting SNIPPE_WEBHOOK_PATH=false in .env
        // and handling webhooks themselves.
        $webhookPath = config('snippe.webhook_path');

        if ($webhookPath && $webhookPath !== 'false') {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }
}
