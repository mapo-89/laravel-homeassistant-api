<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;

class Templates
{
    protected ApiClient $client;
    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Render a Home Assistant template
     */
    public function render(string $template): string
    {
        $response = $this->client->_post('template', [
            'template' => $template,
        ], false);

        /**
         * Home Assistant returns plain text,
         * but Laravel Http may wrap it depending on headers.
         */
        if (is_array($response)) {
            return implode('', $response);
        }

        return (string) $response;
    }
}
