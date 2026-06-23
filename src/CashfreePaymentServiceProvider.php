<?php

namespace CashfreePayment;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class CashfreePaymentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cashfree.php', 'cashfree'
        );

        // Bind CashfreeClient to container
        $this->app->singleton('cashfree', function ($app) {
            $config = $app['config']->get('cashfree');

            $logger = null;
            if ($config['logging_enabled'] ?? true) {
                $channel = $config['log_channel'] ?? null;
                $logger = $channel ? Log::channel($channel) : $app['log'];
            }

            return new CashfreeClient(
                appId: $config['app_id'] ?? '',
                secretKey: $config['secret_key'] ?? '',
                environment: $config['environment'] ?? 'sandbox',
                apiVersion: $config['api_version'] ?? '2023-08-01',
                logger: $logger,
                loggingEnabled: (bool) ($config['logging_enabled'] ?? true),
                retryAttempts: (int) ($config['retry_attempts'] ?? 3),
                retryBackoffMs: (int) ($config['retry_backoff_ms'] ?? 500)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Load package migrations automatically
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish files if running in console
        if ($this->app->runningInConsole()) {
            // Configuration
            $this->publishes([
                __DIR__ . '/../config/cashfree.php' => config_path('cashfree.php'),
            ], 'cashfree-config');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'cashfree-migrations');
        }
    }
}
