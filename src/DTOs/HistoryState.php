<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

use Carbon\Carbon;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class HistoryState
{
    public string $entityId;

    public string $state;

    public Carbon $lastChanged;

    public Carbon $lastUpdated;

    /** @var array<string, mixed> */
    public array $attributes;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $state = new State($data);
        if ($state->last_changed === null) {
            throw new HomeAssistantException('Home Assistant returned an invalid history state.');
        }

        $this->entityId = $state->entity_id;
        $this->state = $state->state;
        $this->attributes = $state->attributes;
        $this->lastChanged = Carbon::instance($state->last_changed);
        $this->lastUpdated = Carbon::instance($state->last_updated ?? $state->last_changed);
    }
}
