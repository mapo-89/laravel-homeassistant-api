<?php
/*
 * HomeAssistantApiServiceProvider.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi;

use Illuminate\Support\ServiceProvider;

class HomeassistantApiServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'homeassistant-api');

        // Register the main class to use with the facade
        $this->app->singleton('homeassistant-api', function () {
            return new HomeassistantApi();
        });

        $this->commands([
            Console\HomeassistantCommand::class,
        ]);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
             // Publishing config.
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('homeassistant-api.php'),
            ], 'config');
        }
    }
}
