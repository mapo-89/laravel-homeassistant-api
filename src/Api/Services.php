<?php
namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class Services extends ApiClient
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }
    /**
     * Call a service in a domain.
     *
     * @param string $domain
     * @param string $service
     * @param array $data
     * @return array
     * @throws HomeAssistantException
     */
    public function call(string $domain, string $service, array $data = []): array
    {
        return $this->client->_post("services/{$domain}/{$service}", $data);
    }

    // =========================== Convenience Methods ====================================

    /**
     * Turn on an entity (light, switch, etc.)
     */
    public function turnOn(string $entityId, array $additionalData = []): array
    {
        [$domain, $id] = explode('.', $entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;
        return $this->call($domain, 'turn_on', $payload);
    }

    /**
     * Turn off an entity (light, switch, etc.)
     */
    public function turnOff(string $entityId, array $additionalData = []): array
    {
        [$domain, $id] = explode('.', $entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;
        return $this->call($domain, 'turn_off', $payload);
    }

    /**
     * Toggle an entity
     */
    public function toggle(string $entityId, array $additionalData = []): array
    {
        [$domain, $id] = explode('.', $entityId);
        $payload = ['entity_id' => $entityId] + $additionalData;
        return $this->call($domain, 'toggle', $payload);
    }
}
