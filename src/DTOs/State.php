<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

use Mapo89\LaravelHomeassistantApi\Api\Utils\ResponseValidator;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class State
{
    public string $entity_id;

    public string $state;

    /** @var array<string, mixed> */
    public array $attributes;

    public ?\DateTimeImmutable $last_changed;

    public ?string $last_changed_raw;

    public ?\DateTimeImmutable $last_reported;

    public ?string $last_reported_raw;

    public ?\DateTimeImmutable $last_updated;

    public ?string $last_updated_raw;

    public ?StateContext $context;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        if (! is_string($data['entity_id'] ?? null) || $data['entity_id'] === ''
            || ! is_string($data['state'] ?? null)
            || (array_key_exists('attributes', $data) && ! is_array($data['attributes']))
            || (isset($data['context']) && ! is_array($data['context']))) {
            throw new HomeAssistantException('Home Assistant returned an invalid state.');
        }
        foreach (['last_changed', 'last_reported', 'last_updated'] as $field) {
            if (isset($data[$field]) && ! is_string($data[$field])) {
                throw new HomeAssistantException('Home Assistant returned an invalid state.');
            }
        }

        $this->entity_id = $data['entity_id'];
        $this->state = $data['state'];
        $this->attributes = $data['attributes'] ?? [];
        $this->last_changed_raw = $data['last_changed'] ?? null;
        $this->last_changed = ResponseValidator::timestamp($this->last_changed_raw, 'state');
        $this->last_reported_raw = $data['last_reported'] ?? null;
        $this->last_reported = ResponseValidator::timestamp($this->last_reported_raw, 'state');
        $this->last_updated_raw = $data['last_updated'] ?? null;
        $this->last_updated = ResponseValidator::timestamp($this->last_updated_raw, 'state');
        $this->context = isset($data['context']) ? new StateContext($data['context']) : null;
    }
}
