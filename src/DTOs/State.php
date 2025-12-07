<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

class State
{
    public string $entity_id;
    public string $state;
    public array $attributes;
    public ?\DateTime $last_changed;
    public ?\DateTime $last_updated;

    public function __construct(array $data)
    {
        $this->entity_id    = $data['entity_id'] ?? '';
        $this->state        = $data['state'] ?? '';
        $this->attributes   = $data['attributes'] ?? [];
        $this->last_changed = isset($data['last_changed']) ? new \DateTime($data['last_changed']) : null;
        $this->last_updated = isset($data['last_updated']) ? new \DateTime($data['last_updated']) : null;
    }
}
