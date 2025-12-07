<?php
/*
 * HomeassistantApi.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;

class HomeassistantApi
{
    protected $client;
    public static function make(?array $configLoader = null): self
    {
        $instance = new static();
        $instance->client = new ApiClient($configLoader);
        return $instance;
    }

    public function __call($method, array $parameters)
    {
        return $this->getApiInstance($method);
    }

    protected function getApiInstance($method)
    {
        $class = "\\Mapo89\\LaravelHomeassistantApi\\Api\\" . ucwords($method);

        if (!class_exists($class)) {
            throw new \BadMethodCallException("Undefined method [{$method}] called.");
        }

        return new $class($this->client);
    }
}
