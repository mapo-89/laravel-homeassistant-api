<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Illuminate\Support\Facades\Cache;
use Throwable;

final class StateCache
{
    public static function remember(array $config, callable $fetch): array
    {
        if (! ($config['cache_responses'] ?? true)) {
            return $fetch();
        }

        $key = self::key($config);
        try {
            $cached = Cache::get($key);
        } catch (Throwable) {
            return $fetch();
        }

        if (is_array($cached)) {
            return $cached;
        }

        // Keep HTTP errors outside the cache handlers; never retry a failed fetch.
        $states = $fetch();
        try {
            Cache::put($key, $states, 5);
        } catch (Throwable) {
            // Caching is optional; the fresh API response remains usable.
        }

        return $states;
    }

    public static function forget(array $config): void
    {
        $key = self::key($config);
        try {
            Cache::forget($key);
        } catch (Throwable) {
            // An unavailable cache must not hide an already successful mutation.
        }
    }

    private static function key(array $config): string
    {
        return 'ha.states.'.hash('sha256', json_encode([rtrim($config['url'], '/'), $config['token']], JSON_THROW_ON_ERROR));
    }
}
