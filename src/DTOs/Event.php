<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class Event
{
    public string $event;

    public string $listenerCount;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        if (! is_string($data['event'] ?? null) || $data['event'] === '') {
            throw new HomeAssistantException('Home Assistant returned an invalid event.');
        }
        $count = $data['listener_count'] ?? 0;
        if ((! is_int($count) || $count < 0)
            && (! is_string($count) || preg_match('/\A[0-9]+\z/', $count) !== 1)) {
            throw new HomeAssistantException('Home Assistant returned an invalid event listener count.');
        }

        $this->event = $data['event'];
        $this->listenerCount = (string) $count;
    }
}
