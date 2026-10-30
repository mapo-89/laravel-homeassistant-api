<?php

/*
 * States.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Illuminate\Support\Facades\Cache;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\DTOs\State;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Mapo89\LaravelHomeassistantApi\Query\StatesQuery;

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

    public function __call($method, $arguments)
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
        $cacheKey = 'ha.states.'.hash('sha256', json_encode([rtrim($config['url'], '/'), $config['token']], JSON_THROW_ON_ERROR));
        $raw = ($config['cache_responses'] ?? true) ? Cache::remember($cacheKey, 5, $fetch) : $fetch();

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
     * Return a single state.
     */
    public function get($entity_id): State
    {
        return new State(
            $this->client->_get('states/'.$entity_id)
        );
    }

    // =========================== create / update ====================================

    /**
     * Create or update a state.
     *
     * @throws HomeAssistantException
     */
    public function createOrUpdate(string $entity_id, string $state, array $attributes = []): State
    {
        $payload = [
            'state' => $state,
            'attributes' => $attributes,
        ];

        $raw = $this->client->_post('states/'.$entity_id, $payload);

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
        $this->client->_delete('states/'.$entity_id);

        return true; // Wenn keine Exception, war es erfolgreich
    }
}
