<?php
/*
 * HomeassistantApi.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi;

class HomeassistantApi
{
    public static function make()
    {
        return new static();
    }

    public function __call($method, array $parameters)
    {
        return $this->getApiInstance($method);
    }

    protected function getApiInstance($method)
    {
        $class = "\\Mapo89\\LaravelHomeassistantApi\\Api\\" . ucwords($method);

        if (class_exists($class)) {
            return new $class();
        }

        throw new \BadMethodCallException("Undefined method [{$method}] called.");
    }
}
