<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

class LogbookEntry
{
    public readonly ?string $state;
    public readonly ?string $name;
    public readonly ?string $entityId;
    public readonly ?string $message;
    public readonly ?string $domain;
    public readonly ?string $contextId;
    public readonly ?string $source;
    public readonly ?string $icon;
    public readonly ?string $when;

    public function __construct(array $data)
    {
        $this->state     = $data['state'] ?? null;
        $this->name      = $data['name'] ?? null;
        $this->entityId  = $data['entity_id'] ?? null;
        $this->message   = $data['message'] ?? null;
        $this->domain    = $data['domain'] ?? null;
        $this->contextId = $data['context_id'] ?? null;
        $this->source    = $data['source'] ?? null;
        $this->icon      = $data['icon'] ?? null;
        $this->when      = $data['when'] ?? null;
    }
}
