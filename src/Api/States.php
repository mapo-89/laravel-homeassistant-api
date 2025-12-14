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
use Mapo89\LaravelHomeassistantApi\Query\StatesQuery;
use Illuminate\Support\Facades\Cache;
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
        $raw = Cache::remember(
            'ha.states',
            now()->addSeconds(5),
            fn () => $this->client->_get('states')
        );

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
     *
     * @param $entity_id
     * @return State
     */
    public function get($entity_id): State 
    {
        return new State(
            $this->client->_get('states/' . $entity_id)
        );
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
