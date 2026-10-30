<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mapo89\LaravelHomeassistantApi\Exceptions\EntityNotFoundException;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Mapo89\LaravelHomeassistantApi\Exceptions\ServiceCallFailedException;
use Mapo89\LaravelHomeassistantApi\Exceptions\UnauthorizedException;

class ApiClient
{
    /** @var array<string, mixed>|null */
    protected ?array $configLoader;

    /**
     * @param  array<string, mixed>|null  $configLoader
     */
    public function __construct(?array $configLoader = null)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * @return array{url: non-empty-string, token: non-empty-string, ...<string, mixed>}
     */
    public function getConfig(): array
    {
        $config = $this->configLoader ?? config('homeassistant-api', []);

        if (! is_array($config) || ! is_string($config['url'] ?? null) || $config['url'] === ''
            || ! is_string($config['token'] ?? null) || trim($config['token']) === '') {
            throw new \InvalidArgumentException('Config must include a nonempty URL and token.');
        }
        foreach (['connect_timeout', 'timeout'] as $key) {
            if (! array_key_exists($key, $config)) {
                continue;
            }
            $value = $config[$key];
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0.001
                || (float) $value >= PHP_INT_MAX / 1000) {
                throw new \InvalidArgumentException('Config option '.$key.' must be a finite timeout of at least 0.001 seconds within the supported range.');
            }
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(string $httpMethod, string $endpoint = '', array $parameters = [], bool $asArray = true): mixed
    {
        $allowedMethods = ['get', 'post', 'put', 'patch', 'delete'];
        $httpMethod = strtolower($httpMethod);
        if (! in_array($httpMethod, $allowedMethods)) {
            throw new \InvalidArgumentException("Invalid HTTP method: {$httpMethod}");
        }

        $config = $this->getConfig();
        $baseUrl = ApiUrl::normalize($config['url']);
        $apiToken = $config['token'];
        // Home Assistant POST endpoints expect objects, even without payload fields.
        $payload = $httpMethod === 'post' && $parameters === [] ? new \stdClass : $parameters;

        try {
            $response = Http::withToken($apiToken)->acceptJson()->withoutRedirecting()
                ->connectTimeout((float) ($config['connect_timeout'] ?? 3))
                ->timeout((float) ($config['timeout'] ?? 8))
                ->$httpMethod("{$baseUrl}{$endpoint}", $payload);
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

        if (! $asArray) {
            return $response->body();
        }

        if ($response->status() === 204 && $response->body() === '') {
            return [];
        }

        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HomeAssistantException('Home Assistant returned an invalid JSON response.');
        }

        if (! is_array($decoded)) {
            throw new HomeAssistantException('Home Assistant returned an invalid JSON response.');
        }

        return $decoded;
    }

    // ========================= base methods ======================================
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function _get(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('get', $url ?? '', $parameters, $asArray);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function _post(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('post', $url ?? '', $parameters, $asArray);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function _put(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('put', $url ?? '', $parameters, $asArray);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function _patch(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('patch', $url ?? '', $parameters, $asArray);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function _delete(?string $url = null, array $parameters = [], bool $asArray = true): mixed
    {
        return $this->execute('delete', $url ?? '', $parameters, $asArray);
    }
}
