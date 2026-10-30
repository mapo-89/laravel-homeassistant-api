<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Console\HomeassistantCommand;
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi as HomeassistantApiFacade;
use Mapo89\LaravelHomeassistantApi\HomeassistantApi;
use Mapo89\LaravelHomeassistantApi\HomeassistantApiServiceProvider;
use Orchestra\Testbench\TestCase;

final class HomeassistantApiServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [HomeassistantApiServiceProvider::class];
    }

    public function test_merges_defaults_without_overwriting_application_configuration(): void
    {
        $this->assertArrayHasKey('url', config('homeassistant-api'));
        $this->assertArrayHasKey('token', config('homeassistant-api'));

        config(['homeassistant-api' => ['url' => 'https://configured.example.test']]);
        $this->app->register(HomeassistantApiServiceProvider::class, true);

        $this->assertSame('https://configured.example.test', config('homeassistant-api.url'));
        $this->assertArrayHasKey('token', config('homeassistant-api'));

        config(['homeassistant-api.token' => 'synthetic-token']);
        $this->assertSame([
            'url' => 'https://configured.example.test',
            'token' => 'synthetic-token',
            'connect_timeout' => 3,
            'timeout' => 8,
            'cache_responses' => true,
        ], (new ApiClient)->getConfig());
    }

    public function test_both_publish_tags_point_to_the_existing_configuration_namespace(): void
    {
        $source = realpath(__DIR__.'/../config/homeassistant-api.php');
        $this->assertNotFalse($source);

        foreach (['homeassistant-api-config', 'config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(HomeassistantApiServiceProvider::class, $tag);
            $this->assertCount(1, $paths);
            $this->assertSame($source, realpath(array_key_first($paths)));
            $this->assertSame(config_path('homeassistant-api.php'), reset($paths));
        }
    }

    public function test_facade_and_container_resolve_the_same_singleton(): void
    {
        $service = $this->app->make(HomeassistantApi::class);

        $this->assertSame($service, $this->app->make(HomeassistantApi::class));
        $this->assertSame($service, $this->app->make('homeassistant-api'));
        $this->assertSame($service, HomeassistantApiFacade::getFacadeRoot());

        $dynamic = HomeassistantApiFacade::make([
            'url' => 'https://other.example.test',
            'token' => 'other-synthetic-token',
        ]);
        $this->assertNotSame($service, $dynamic);
        $this->assertSame($service, HomeassistantApiFacade::getFacadeRoot());
    }

    public function test_registers_the_existing_artisan_command(): void
    {
        $this->assertInstanceOf(HomeassistantCommand::class, Artisan::all()['ha:call']);
        $this->artisan('ha:call', ['--help' => true])->assertExitCode(0);
    }
}
