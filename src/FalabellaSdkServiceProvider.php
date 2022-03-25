<?php

namespace Astroselling\FalabellaSdk;

use Astroselling\FalabellaSdk\Facades\FalabellaSdk;
use Illuminate\Support\ServiceProvider;

class FalabellaSdkServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/falabellasdk.php', 'falabellasdk');

        // Register the service the package provides.
        $this->app->singleton('falabellasdk', function ($app) {
            return new FalabellaSdk;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['falabellasdk'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/falabellasdk.php' => config_path('falabellasdk.php'),
        ], 'falabellasdk.config');
    }
}
