<?php

namespace Mapo89\LaravelHomeassistantApi\Api;

use Carbon\CarbonInterface;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\DTOs\LogbookEntry;

class Logbook
{
    protected ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get logbook entries
     *
     * @param  array<string>  $entityIds
     * @param  CarbonInterface|null  $start
     * @param  CarbonInterface|null  $end
     * @return LogbookEntry[]
     */
    public function get(
        array $entityIds,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null
    ): array {
        // Fallback: last 24 hours
        $start ??= now()->subDay();

        $params = [
            'start_time' => $start->toIso8601String(),
            'entity'     => implode(',', $entityIds),
        ];

        if ($end) {
            $params['end_time'] = $end->toIso8601String();
        }

        $raw = $this->client->_get('logbook', $params);

        return array_map(
            fn (array $entry) => new LogbookEntry($entry),
            $raw
        );
    }

}
