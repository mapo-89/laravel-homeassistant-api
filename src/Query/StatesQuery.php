<?php

namespace Mapo89\LaravelHomeassistantApi\Query;

use Mapo89\LaravelHomeassistantApi\DTOs\State;
use Illuminate\Support\Collection;

class StatesQuery
{
    private Collection $states;

    public function __construct(array $rawStates)
    {
        $this->states = collect($rawStates);
    }

    public function whereDomain(string $domain): self
    {
        $this->states = $this->states->filter(
            fn ($state) => str_starts_with($state['entity_id'], $domain . '.')
        );

        return $this;
    }

    public function whereState(string $state): self
    {
        $this->states = $this->states->filter(
            fn ($item) => $item['state'] === $state
        );

        return $this;
    }

    public function whereEntityIds(array $entityIds): self
    {
        $this->states = $this->states->filter(
            fn ($item) => in_array($item['entity_id'], $entityIds, true)
        );

        return $this;
    }

    public function whereAttribute(string $key, mixed $value): self
    {
        $this->states = $this->states->filter(
            fn ($item) => ($item['attributes'][$key] ?? null) === $value
        );

        return $this;
    }

    public function get(): array
    {
        return $this->states
            ->map(fn ($state) => new State($state))
            ->values()
            ->all();
    }

    public function first(): ?State
    {
        $state = $this->states->first();

        return $state ? new State($state) : null;
    }
}
