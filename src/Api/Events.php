<?php

/*
 * Events.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ResponseValidator;
use Mapo89\LaravelHomeassistantApi\DTOs\Event;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class Events
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get all available Home Assistant events
     *
     * GET /api/events
     *
     * @return Event[]
     */
    public function all(): array
    {
        $raw = ResponseValidator::records($this->client->_get('events'), 'events');

        return array_map(
            fn (array $event) => new Event($event),
            $raw
        );
    }

    /**
     * Fire a custom Home Assistant event
     *
     * POST /api/events/{event_type}
     *
     * @param  array<string, mixed>  $data
     */
    public function fire(string $eventType, array $data = []): bool
    {
        $response = $this->client->_post("events/{$eventType}", $data);
        if (! is_string($response['message'] ?? null) || $response['message'] === '') {
            throw new HomeAssistantException('Home Assistant returned an invalid event response.');
        }

        return true;
    }
}
