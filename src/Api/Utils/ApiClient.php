<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mapo89\LaravelHomeassistantApi\Exceptions\{
    HomeAssistantException,
    UnauthorizedException,
    EntityNotFoundException,
    ServiceCallFailedException
};

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
        if (!Str::endsWith($url, '/api')) {
            $url .= '/api';
        }
        return $url . '/';
    }

    public function getConfig(): array
    {
        $config = $this->configLoader ?? [
            'url' => config('homeassistant-api.url'),
            'token' => config('homeassistant-api.token'),
        ];

        if (!isset($config['url'], $config['token'])) {
            throw new \InvalidArgumentException("Config must include 'url' and 'token'");
        }

        return $config;
    }

    public function execute(string $httpMethod, string $endpoint = '', array $parameters = []): array
    {
        $allowedMethods = ['get','post','put','patch','delete'];
        $httpMethod = strtolower($httpMethod);
        if (!in_array($httpMethod, $allowedMethods)) {
            throw new \InvalidArgumentException("Invalid HTTP method: {$httpMethod}");
        }

        $config = $this->getConfig();
        $baseUrl = $this->normalizeBaseUrl($config['url']);
        $apiToken = $config['token'];

        $response = Http::withToken($apiToken)->$httpMethod("{$baseUrl}{$endpoint}", $parameters);

        if ($response->status() === 401) {
            throw new UnauthorizedException("Unauthorized: Check your token");
        }

        if ($response->status() === 404) {
            throw new EntityNotFoundException("Entity not found: {$endpoint}");
        }

        if ($response->status() >= 400) {
            throw new ServiceCallFailedException(
                "Request failed ({$response->status()}): " . $response->body()
            );
        }

        return $response->json();
    }

    // ========================= base methods ======================================
    public function _get(?string $url = null, array $parameters = []): array
    {
        return $this->execute('get', $url ?? '', $parameters);
    }

    public function _post(?string $url = null, array $parameters = []): array
    {
        return $this->execute('post', $url ?? '', $parameters);
    }

    public function _put(?string $url = null, array $parameters = []): array
    {
        return $this->execute('put', $url ?? '', $parameters);
    }

    public function _patch(?string $url = null, array $parameters = []): array
    {
        return $this->execute('patch', $url ?? '', $parameters);
    }

    public function _delete(?string $url = null, array $parameters = []): array
    {
        return $this->execute('delete', $url ?? '', $parameters);
    }
}
