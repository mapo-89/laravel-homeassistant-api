<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mapo89\LaravelHomeassistantApi\Exceptions\EntityNotFoundException;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Mapo89\LaravelHomeassistantApi\Exceptions\ServiceCallFailedException;
use Mapo89\LaravelHomeassistantApi\Exceptions\UnauthorizedException;

class ApiClient
{
    protected ?array $configLoader;

    public function __construct(?array $configLoader = null)
    {
        $this->configLoader = $configLoader;
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = rtrim($url, '/');
        if (! Str::endsWith($url, '/api')) {
            $url .= '/api';
        }

        return $url.'/';
    }

    public function getConfig(): array
    {
        $config = $this->configLoader ?? config('homeassistant-api', []);

        if (! isset($config['url'], $config['token'])) {
            throw new \InvalidArgumentException("Config must include 'url' and 'token'");
        }

        return $config;
    }

    public function execute(string $httpMethod, string $endpoint = '', array $parameters = [], bool $asArray = true): mixed
    {
        $allowedMethods = ['get', 'post', 'put', 'patch', 'delete'];
        $httpMethod = strtolower($httpMethod);
        if (! in_array($httpMethod, $allowedMethods)) {
            throw new \InvalidArgumentException("Invalid HTTP method: {$httpMethod}");
        }

        $config = $this->getConfig();
        $baseUrl = $this->normalizeBaseUrl($config['url']);
        $apiToken = $config['token'];

        try {
            $response = Http::withToken($apiToken)->acceptJson()->withoutRedirecting()
                ->connectTimeout((float) ($config['connect_timeout'] ?? 3))
                ->timeout((float) ($config['timeout'] ?? 8))
                ->$httpMethod("{$baseUrl}{$endpoint}", $parameters);
        } catch (ConnectionException) {
            throw new HomeAssistantException('Home Assistant could not be reached.');
        }

        if ($response->status() === 401) {
            throw new UnauthorizedException('Unauthorized: Check your token', 401);
        }

        if ($response->status() === 404) {
            throw new EntityNotFoundException("Entity not found: {$endpoint}", 404);
        }

        if (! $response->successful()) {
            throw new ServiceCallFailedException(
                "Home Assistant returned HTTP {$response->status()}.", $response->status()
            );
        }

        return $asArray ? $response->json() : $response->body();
    }

    // ========================= base methods ======================================
    public function _get(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('get', $url ?? '', $parameters, $asArray);
    }

    public function _post(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('post', $url ?? '', $parameters, $asArray);
    }

    public function _put(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('put', $url ?? '', $parameters, $asArray);
    }

    public function _patch(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('patch', $url ?? '', $parameters, $asArray);
    }

    public function _delete(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('delete', $url ?? '', $parameters, $asArray);
    }
}
