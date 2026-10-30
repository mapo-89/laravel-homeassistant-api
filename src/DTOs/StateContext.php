<?php

namespace Mapo89\LaravelHomeassistantApi\DTOs;

use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class StateContext
{
    public string $id;

    public ?string $parent_id;

    public ?string $user_id;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        foreach (['id', 'parent_id', 'user_id'] as $field) {
            if (isset($data[$field]) && ! is_string($data[$field])) {
                throw new HomeAssistantException('Home Assistant returned an invalid state context.');
            }
        }

        $this->id = $data['id'] ?? '';
        $this->parent_id = $data['parent_id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
    }
}
