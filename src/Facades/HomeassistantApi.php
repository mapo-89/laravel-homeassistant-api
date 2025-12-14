<?php
/*
 * HomeassistantApi.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Facades;

use Illuminate\Support\Facades\Facade;

class HomeassistantApi extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'homeassistant-api';
    }
}
