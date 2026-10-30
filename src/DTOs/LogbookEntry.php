<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ResponseValidator;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        if (! is_string($data['when'] ?? null) || $data['when'] === '') {
            throw new HomeAssistantException('Home Assistant returned an invalid logbook entry.');
        }
        foreach (['state', 'name', 'entity_id', 'message', 'domain', 'context_id', 'source', 'icon'] as $field) {
            if (isset($data[$field]) && ! is_string($data[$field])) {
                throw new HomeAssistantException('Home Assistant returned an invalid logbook entry.');
            }
        }
        ResponseValidator::timestamp($data['when'], 'logbook');

        $this->state = $data['state'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->entityId = $data['entity_id'] ?? null;
        $this->message = $data['message'] ?? null;
        $this->domain = $data['domain'] ?? null;
        $this->contextId = $data['context_id'] ?? null;
        $this->source = $data['source'] ?? null;
        $this->icon = $data['icon'] ?? null;
        $this->when = $data['when'];
    }
}
