<?php

/*
 * States.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Api\Utils\EntityId;
use Mapo89\LaravelHomeassistantApi\Api\Utils\StateCache;
use Mapo89\LaravelHomeassistantApi\DTOs\State;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Mapo89\LaravelHomeassistantApi\Query\StatesQuery;

/**
 * @mixin StatesQuery
 *
 * Homeassistant REST Api
 *
 * @see https://developers.home-assistant.io/docs/api/rest
 */
class States
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
     * @param  list<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->query()->{$method}(...$arguments);
    }

    public function query(): StatesQuery
    {
        $config = $this->client->getConfig();
        $fetch = function (): array {
            $states = $this->client->_get('states');
            if (! is_array($states) || ! array_is_list($states)) {
                throw new HomeAssistantException('Home Assistant returned invalid states.');
            }
            foreach ($states as $state) {
                if (! is_array($state) || ! is_string($state['entity_id'] ?? null) || ! is_string($state['state'] ?? null) || ! is_array($state['attributes'] ?? [])) {
                    throw new HomeAssistantException('Home Assistant returned invalid states.');
                }
            }

            return $states;
        };
        $raw = StateCache::remember($config, $fetch);

        return new StatesQuery($raw);
    }
    // =========================== all ====================================

    /**
     * Return all states.
     *
     * @return State[]
     */
    public function all(): array
    {
        return $this->query()->get();
    }

    // =========================== get ====================================

    /**
     * Return one state, or all states when no entity ID is supplied.
     *
     * @return State|State[]
     *
     * @phpstan-return ($entity_id is null ? list<State> : State)
     */
    public function get(?string $entity_id = null): State|array
    {
        if ($entity_id === null) {
            return $this->all();
        }
        EntityId::domain($entity_id);

        return new State(
            $this->client->_get('states/'.$entity_id)
        );
    }

    // =========================== create / update ====================================

    /**
     * Create or update a state.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws HomeAssistantException
     */
    public function createOrUpdate(string $entity_id, string $state, array $attributes = []): State
    {
        EntityId::domain($entity_id);
        $payload = [
            'state' => $state,
            'attributes' => $attributes,
        ];

        $raw = $this->client->_post('states/'.$entity_id, $payload);
        StateCache::forget($this->client->getConfig());

        return new State($raw);
    }

    // =========================== delete ====================================

    /**
     * Delete a custom state.
     *
     * @throws HomeAssistantException
     */
    public function delete(string $entity_id): bool
    {
        EntityId::domain($entity_id);
        $this->client->_delete('states/'.$entity_id);
        StateCache::forget($this->client->getConfig());

        return true; // Wenn keine Exception, war es erfolgreich
    }
}
