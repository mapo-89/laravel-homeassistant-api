<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Api\Utils\WebSocketClient;

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

    /** @return array<string, mixed> */
    public function setExternalUrl(string $externalUrl): array
    {
        $config = $this->client->getConfig();

        return (new WebSocketClient)->setExternalUrl($config['url'], $config['token'], $externalUrl);
    }

    public function check(): array
    {
        return $this->client->_post('config/core/check_config');
    }
}
