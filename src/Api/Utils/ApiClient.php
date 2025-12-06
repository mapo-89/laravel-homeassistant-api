<?php
/*
 * ApiClient.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Illuminate\Validation\UnauthorizedException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;

class ApiClient
{
    protected $client;
    protected $baseUrl;
    protected $apiToken;

    public function __construct()
    {
        $this->baseUrl = $this->normalizeBaseUrl(
            config('homeassistant-api.url', 'http://homeassistant.local:8123/api/')
        );
        $this->apiToken = config('homeassistant-api.token');
        $this->client = $client ?? new Client(['base_uri' => $this->baseUrl]);
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = rtrim($url, '/');

        if (!str_ends_with($url, '/api')) {
            $url .= '/api';
        }

        return $url . '/';
    }

    private function getClient(): Client
    {
        return $this->client;
    }

    private function getToken(): string
    {
        return $this->apiToken;
    }

    public function execute(string $httpMethod, string $endpoint = '', array $parameters = [])
    {
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Accept'        => 'application/json'
            ];
            
            $response = $this->getClient()->{$httpMethod}($endpoint, [
                'headers' => $headers,
                'json' => $parameters
            ]);

            return $this->handleResponse($response);
        } catch (BadResponseException $exception) {
            $this->handleException($exception);
        }

    }

    // ========================= Response methods ======================================

    private function handleResponse($response): array
    {
        $responseBody = json_decode((string)$response->getBody(), true);

        if (isset($responseBody['error'])) {
            throw new \Exception($responseBody['message'] ?? 'Unknown error');
        }
        if (isset($responseBody['status']) && $responseBody['status'] == 400) {
            throw new UnauthorizedException($responseBody['message'] ?? 'Unauthorized');
        }

        return $responseBody;
    }

    private function handleException($exception): void
    {
        $raw = (string)$exception->getResponse()->getBody();
        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

            if (isset($decoded['error'])) {
                throw new \Exception($decoded['message'] ?? $decoded['error']);
            }

            if (($decoded['status'] ?? null) == 400) {
                throw new UnauthorizedException($decoded['message'] ?? 'Unauthorized');
            }

            throw new \Exception('API Error: ' . ($decoded['message'] ?? 'Unknown JSON error'));
        }

        throw new \Exception("API Error: {$raw}");
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
