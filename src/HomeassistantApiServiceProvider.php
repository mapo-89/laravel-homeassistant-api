<?php

/*
 * HomeAssistantApiServiceProvider.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi;

use Mapo89\LaravelHomeassistantApi\Console\HomeassistantCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HomeassistantApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-homeassistant-api')
            ->hasConfigFile()
            ->hasCommand(HomeassistantCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(HomeassistantApi::class);
        $this->app->alias(HomeassistantApi::class, 'homeassistant-api');
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            // Preserve the original publish tag for existing applications.
            $this->publishes(static::pathsToPublish(static::class, 'homeassistant-api-config'), 'config');
        }
    }
}
