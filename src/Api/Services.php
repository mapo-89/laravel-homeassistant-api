<?php
namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Api\Utils\StateCache;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class Services
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    public function getConfig(): array
    {
        return $this->client->getConfig();
    }
    /**
     * Call a service in a domain.
     *
     * @param string $domain
     * @param string $service
     * @param array $data
     * @param bool $returnResponse Request changed_states and service_response.
     * @return array
     * @throws HomeAssistantException
     */
    public function call(string $domain, string $service, array $data = [], bool $returnResponse = false): array
    {
        $endpoint = "services/{$domain}/{$service}".($returnResponse ? '?return_response' : '');
        $response = $this->client->_post($endpoint, $data);
        StateCache::forget($this->client->getConfig());

        return $response;
    }

    // =========================== Convenience Methods ====================================

    /**
     * Turn on an entity (light, switch, etc.)
     */
    public function turnOn(string $entityId, array $additionalData = []): array
    {
        $domain = $this->entityDomain($entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;
        return $this->call($domain, 'turn_on', $payload);
    }

    /**
     * Turn off an entity (light, switch, etc.)
     */
    public function turnOff(string $entityId, array $additionalData = []): array
    {
        $domain = $this->entityDomain($entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;
        return $this->call($domain, 'turn_off', $payload);
    }

    /**
     * Toggle an entity
     */
    public function toggle(string $entityId, array $additionalData = []): array
    {
        $domain = $this->entityDomain($entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;
        return $this->call($domain, 'toggle', $payload);
    }

    private function entityDomain(string $entityId): string
    {
        if (preg_match('/\A(?!.*__)((?!_)[a-z0-9_]+(?<!_))\.(?!_)[a-z0-9_]+(?<!_)\z/', $entityId, $matches) !== 1) {
            throw new \InvalidArgumentException('Entity ID must use the format domain.object_id.');
        }

        return $matches[1];
    }
}
