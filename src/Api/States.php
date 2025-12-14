<?php
/*
 * States.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\DTOs\State;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

/**
 * Homeassistant REST Api
 *
 * @see https://developers.home-assistant.io/docs/api/rest
 */
class States extends ApiClient
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    private function mapStates(array $raw): array
    {
        return array_map(fn ($state) => new State($state), $raw);
    }

    // =========================== all ====================================

    /**
     * Return all states.
     *
     * @return State[]
     */
    public function all(): array
    {
        return $this->mapStates(
        $this->client->_get('states')
        );
    }

    // =========================== filterByEntities ===========================

    /**
     * Return only states matching given entity_ids.
     *
     * @param string[] $entityIds
     * @return State[]
     */
    public function filterByEntities(array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        $raw = array_filter(
            $this->client->_get('states'),
            fn ($state) => in_array($state['entity_id'], $entityIds, true)
        );

        return $this->mapStates(array_values($raw));
    }

    // =========================== filterByDomain ===========================

    /**
     * Return only states matching given domains.
     *
     * @param string[] $domain
     * @return State[]
     */
    public function filterByDomain(string $domain): array
    {
        $raw = $this->client->_get('states');

        return $this->mapStates(array_filter(
            $raw,
            fn ($state) => str_starts_with($state['entity_id'], $domain . '.')
        ));
    }


    // =========================== get ====================================

    /**
     * Return a single state.
     *
     * @param $entity_id
     * @return State
     */
    public function get($entity_id)
    {
        $raw = $this->client->_get('states/' . $entity_id);
        return new State($raw);
    }

    // =========================== create / update ====================================

    /**
     * Create or update a state.
     *
     * @param string $entity_id
     * @param string $state
     * @param array $attributes
     * @return State
     * @throws HomeAssistantException
     */
    public function createOrUpdate(string $entity_id, string $state, array $attributes = []): State
    {
        $payload = [
            'state' => $state,
            'attributes' => $attributes
        ];

        $raw = $this->client->_post('states/' . $entity_id, $payload);
        return new State($raw);
    }

    // =========================== delete ====================================

    /**
     * Delete a custom state.
     *
     * @param string $entity_id
     * @return bool
     * @throws HomeAssistantException
     */
    public function delete(string $entity_id): bool
    {
        $this->client->_delete('states/' . $entity_id);
        return true; // Wenn keine Exception, war es erfolgreich
    }
}
