<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

final class EntityId
{
    public static function domain(string $entityId): string
    {
        if (preg_match('/\A(?!.*__)((?!_)[a-z0-9_]+(?<!_))\.(?!_)[a-z0-9_]+(?<!_)\z/', $entityId, $matches) !== 1) {
            throw new \InvalidArgumentException('Entity ID must use the format domain.object_id.');
        }

        return $matches[1];
    }

    /**
     * @param  list<mixed>  $entityIds
     */
    public static function requireList(array $entityIds, string $endpoint): void
    {
        if ($entityIds === []) {
            throw new \InvalidArgumentException($endpoint.' queries require at least one entity ID.');
        }
        foreach ($entityIds as $entityId) {
            if (! is_string($entityId)) {
                throw new \InvalidArgumentException($endpoint.' entity IDs must use the format domain.object_id.');
            }
            try {
                self::domain($entityId);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException($endpoint.' entity IDs must use the format domain.object_id.');
            }
        }
    }
}
