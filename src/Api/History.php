<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Carbon\Carbon;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\DTOs\HistoryState;

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
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @param array $entityIds
     * @param bool $significantOnly
     * @param bool $minimal
     *
     * @return array<string, HistoryState[]>
     */
    public function get(
        ?Carbon $start = null,
        ?Carbon $end = null,
        array $entityIds = [],
        bool $significantOnly = false,
        bool $minimal = false
    ): array {
        $endpoint = 'history/period';

        if ($start) {
            $endpoint .= '/' . $start->toIso8601String();
        }

        $query = [];

        if ($end) {
            $query['end_time'] = $end->toIso8601String();
        }

        if (!empty($entityIds)) {
            $query['filter_entity_id'] = implode(',', $entityIds);
        }

        if ($significantOnly) {
            $query['significant_changes_only'] = '1';
        }

        if ($minimal) {
            $query['minimal_response'] = '1';
        }

        $raw = $this->client->_get($endpoint, $query);

        return $this->mapResponse($raw);
    }

    /**
     * Flatten history into a single list
     *
     * @return HistoryState[]
     */
    public function flat(
        ?Carbon $start = null,
        ?Carbon $end = null,
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
     * @param array $raw
     * @return array<string, HistoryState[]>
     */
    protected function mapResponse(array $raw): array
    {
        $result = [];

        foreach ($raw as $entityStates) {
            if (empty($entityStates)) {
                continue;
            }

            $entityId = $entityStates[0]['entity_id'];

            $result[$entityId] = array_map(
                fn (array $state) => new HistoryState($state),
                $entityStates
            );
        }

        return $result;
    }
}
