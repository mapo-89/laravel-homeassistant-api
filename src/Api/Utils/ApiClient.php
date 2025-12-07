<?php
/*
 * ApiClient.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Illuminate\Support\Facades\Http;
use Mapo89\LaravelHomeassistantApi\Exceptions\{
    HomeAssistantException,
    UnauthorizedException,
    EntityNotFoundException,
    ServiceCallFailedException
};

class ApiClient
{
    protected $configLoader;

    public function __construct(?array $configLoader = null)
    {
        $this->configLoader = $configLoader;
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = rtrim($url, '/');

        if (!str_ends_with($url, '/api')) {
            $url .= '/api';
        }

        return $url . '/';
    }

    /**
     * Get URL & Token
     */
    public function getConfig(): array
    {
        if ($this->configLoader) {
            $config =$this->configLoader;
            
            if (!isset($config['url'], $config['token'])) {
                throw new \InvalidArgumentException("Config loader must return ['url', 'token']");
            }
            return $config;
        }

        // fallback auf config/env
        return [
            'url' => config('homeassistant-api.url'),
            'token' => config('homeassistant-api.token')
        ];
    }

    public function execute(string $httpMethod, string $endpoint = '', array $parameters = [])
    {
        $config = $this->getConfig();
        $baseUrl = $this->normalizeBaseUrl($config['url']);
        $apiToken = $config['token'];

        $response = Http::withToken($apiToken)
                        ->$httpMethod("{$baseUrl}{$endpoint}", $parameters);

        if ($response->status() === 401) {
            throw new UnauthorizedException("Unauthorized: Check your token");
        }

        if ($response->status() === 404) {
            throw new EntityNotFoundException("Entity not found: {$endpoint}");
        }

        if ($response->status() >= 400) {
            throw new ServiceCallFailedException("Request failed with status {$response->status()}");
        }

        return $response->json();
    }

    // ========================= base methods ======================================

    public function _get($url = null, $parameters = [])
    {
        return $this->execute('get', $url, $parameters);
    }

    public function _post($url = null, array $parameters = [])
    {
        return $this->execute('post', $url, $parameters);
    }

    public function _put($url = null, array $parameters = [])
    {
        return $this->execute('put', $url, $parameters);
    }

    public function _patch($url = null, array $parameters = [])
    {
        return $this->execute('patch', $url, $parameters);
    }

    public function _delete($url = null, array $parameters = [])
    {
        return $this->execute('delete', $url, $parameters);
    }
}
