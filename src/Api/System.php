<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;

class System
{
    protected ApiClient $client;
    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    public function verify(): array
    {
        return $this->client->_get();
    }
}
