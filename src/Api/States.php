<?php
/*
 * States.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\DTOs\State;

/**
 * Homeassistant REST Api
 *
 * @see https://developers.home-assistant.io/docs/api/rest
 */
class States extends ApiClient
{
    // =========================== all ====================================

    /**
     * Return all states.
     *
     * @return State[]
     */
    public function getStates(): array
    {
        $raw = $this->_get('states');
        return array_map(fn($state) => new State($state), $raw);
    }

    // =========================== get ====================================

    /**
     * Return a single state.
     *
     * @param $entity_id
     * @return mixed
     */
    public function get($entity_id)
    {
        return $this->_get('states/' . $entity_id);
    }
}
