<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Carbon\CarbonInterface;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Api\Utils\EntityId;
use Mapo89\LaravelHomeassistantApi\DTOs\HistoryState;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

class History
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get state history
     *
     *
     * @param  list<string>  $entityIds
     * @return array<string, HistoryState[]>
     */
    public function get(
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        array $entityIds = [],
        bool $significantOnly = false,
        bool $minimal = false
    ): array {
        EntityId::requireList($entityIds, 'History');

        $endpoint = 'history/period';

        if ($start) {
            $endpoint .= '/'.$start->toIso8601String();
        }

        $query = ['significant_changes_only' => $significantOnly ? '1' : '0'];

        if ($end) {
            $query['end_time'] = $end->toIso8601String();
        }

        $query['filter_entity_id'] = implode(',', $entityIds);

        if ($minimal) {
            $query['minimal_response'] = '1';
        }

        $raw = $this->client->_get($endpoint, $query);

        return $this->mapResponse($raw);
    }

    /**
     * Flatten history into a single list
     *
     * @param  list<string>  $entityIds
     * @return HistoryState[]
     */
    public function flat(
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        array $entityIds = [],
        bool $significantOnly = false,
        bool $minimal = false
    ): array {
        return collect(
            $this->get($start, $end, $entityIds, $significantOnly, $minimal)
        )
            ->flatten()
            ->values()
            ->all();
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @return array<string, HistoryState[]>
     */
    protected function mapResponse(array $raw): array
    {
        if (! array_is_list($raw)) {
            throw new HomeAssistantException('Home Assistant returned invalid history.');
        }
        $result = [];

        foreach ($raw as $entityStates) {
            if (! is_array($entityStates) || ! array_is_list($entityStates)) {
                throw new HomeAssistantException('Home Assistant returned invalid history.');
            }
            if ($entityStates === []) {
                continue;
            }
            if (! is_array($entityStates[0]) || ! is_string($entityStates[0]['entity_id'] ?? null)
                || $entityStates[0]['entity_id'] === '') {
                throw new HomeAssistantException('Home Assistant returned invalid history.');
            }
            $entityId = $entityStates[0]['entity_id'];
            $result[$entityId] = [];
            foreach ($entityStates as $state) {
                if (! is_array($state) || (array_key_exists('entity_id', $state) && $state['entity_id'] !== $entityId)) {
                    throw new HomeAssistantException('Home Assistant returned invalid history.');
                }
                $result[$entityId][] = new HistoryState($state + ['entity_id' => $entityId]);
            }
        }

        return $result;
    }
}
