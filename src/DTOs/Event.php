<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

class Event
{
    public string $event;
    public string $listenerCount;

    public function __construct(array $data)
    {
        $this->event = $data['event'];
        $this->listenerCount = (string) ($data['listener_count'] ?? '0');
    }
}
