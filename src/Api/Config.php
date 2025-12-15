<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;

class Config
{
    protected ApiClient $client;
    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    public function get(): array
    {
        return $this->client->_get('config');
    }

    public function check(): array
    {
        return $this->client->_post('config/core/check_config');
    }
}
