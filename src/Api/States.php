<?php
/*
 * States.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Illuminate\Support\Collection;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;

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
     * @return Collection
     */
    public function all(): Collection
    {
        return Collection::make($this->_get('states'));
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
