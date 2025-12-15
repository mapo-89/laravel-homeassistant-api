<?php
/*
 * Events.php
 * @author Manuel Postler <info@postler.de>
 * @copyright 2025 Manuel Postler
 */

namespace Mapo89\LaravelHomeassistantApi\Api;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\DTOs\Event;

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
        $raw = $this->client->_get('events');

        return array_map(
            fn (array $event) => new Event($event),
            $raw
        );
    }

    /**
     * Fire a custom Home Assistant event
     *
     * POST /api/events/{event_type}
     */
    public function fire(string $eventType, array $data = []): bool
    {
        $this->client->_post("events/{$eventType}", $data);

        return true;
    }
}
