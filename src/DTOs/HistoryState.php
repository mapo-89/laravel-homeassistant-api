<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

use Carbon\Carbon;

class HistoryState
{
    public string $entityId;
    public string $state;
    public Carbon $lastChanged;
    public Carbon $lastUpdated;
    public array $attributes;

    public function __construct(array $data)
    {
        $this->entityId = $data['entity_id'];
        $this->state = (string) $data['state'];
        $this->attributes = $data['attributes'] ?? [];

        $this->lastChanged = Carbon::parse($data['last_changed']);
        $this->lastUpdated = Carbon::parse($data['last_updated']);
    }
}
