<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Api\Utils\EntityId;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ResponseValidator;
use Mapo89\LaravelHomeassistantApi\Api\Utils\StateCache;
use Mapo89\LaravelHomeassistantApi\DTOs\State;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class Services
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->client->getConfig();
    }

    /**
     * Call a service in a domain.
     *
     * @param  bool  $returnResponse  Request changed_states and service_response.
     * @param  array<string, mixed>  $data
     * @return array<array-key, mixed>
     *
     * @throws HomeAssistantException
     */
    public function call(string $domain, string $service, array $data = [], bool $returnResponse = false): array
    {
        $endpoint = "services/{$domain}/{$service}".($returnResponse ? '?return_response' : '');
        $response = $this->client->_post($endpoint, $data);
        StateCache::forget($this->client->getConfig());

        $states = $response;
        if ($returnResponse) {
            if (! is_array($response['changed_states'] ?? null)
                || ! array_key_exists('service_response', $response)
                || ($response['service_response'] !== null && ! is_array($response['service_response']))
                || (is_array($response['service_response']) && $response['service_response'] !== []
                    && array_is_list($response['service_response']))) {
                throw new HomeAssistantException('Home Assistant returned an invalid service response.');
            }
            $states = $response['changed_states'];
        }
        foreach (ResponseValidator::records($states, 'service states') as $state) {
            new State($state);
        }

        return $response;
    }

    // =========================== Convenience Methods ====================================

    /**
     * Turn on an entity (light, switch, etc.)
     *
     * @param  array<string, mixed>  $additionalData
     * @return array<array-key, mixed>
     */
    public function turnOn(string $entityId, array $additionalData = []): array
    {
        $domain = EntityId::domain($entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;

        return $this->call($domain, 'turn_on', $payload);
    }

    /**
     * Turn off an entity (light, switch, etc.)
     *
     * @param  array<string, mixed>  $additionalData
     * @return array<array-key, mixed>
     */
    public function turnOff(string $entityId, array $additionalData = []): array
    {
        $domain = EntityId::domain($entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;

        return $this->call($domain, 'turn_off', $payload);
    }

    /**
     * Toggle an entity
     *
     * @param  array<string, mixed>  $additionalData
     * @return array<array-key, mixed>
     */
    public function toggle(string $entityId, array $additionalData = []): array
    {
        $domain = EntityId::domain($entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;

        return $this->call($domain, 'toggle', $payload);
    }
}
