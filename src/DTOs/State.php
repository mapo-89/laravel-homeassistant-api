<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

class State
{
    public string $entity_id;
    public string $state;
    public array $attributes;
    public ?\DateTimeImmutable $last_changed;
    public ?string $last_changed_raw;
    public ?\DateTimeImmutable $last_reported;
    public ?string $last_reported_raw;
    public ?\DateTimeImmutable $last_updated;
    public ?string $last_updated_raw;
    public ?StateContext $context;

    public function __construct(array $data)
    {
        $this->entity_id = $data['entity_id'] ?? '';
        $this->state = $data['state'] ?? '';
        $this->attributes = $data['attributes'] ?? [];
        $this->last_changed_raw = $data['last_changed'] ?? null;
        $this->last_changed = $this->last_changed_raw ? new \DateTimeImmutable($this->last_changed_raw) : null;
        $this->last_reported_raw = $data['last_reported'] ?? null;
        $this->last_reported = $this->last_reported_raw ? new \DateTimeImmutable($this->last_reported_raw) : null;
        $this->last_updated_raw = $data['last_updated'] ?? null;
        $this->last_updated = $this->last_updated_raw ? new \DateTimeImmutable($this->last_updated_raw) : null;
        $this->context = isset($data['context']) ? new StateContext($data['context']) : null;
    }
}

class StateContext
{
    public string $id;
    public ?string $parent_id;
    public ?string $user_id;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? '';
        $this->parent_id = $data['parent_id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
    }
}
