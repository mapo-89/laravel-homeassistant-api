<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

final class ApiUrl
{
    public static function normalize(string $url): string
    {
        try {
            $parts = parse_url($url);
        } catch (\ValueError) {
            $parts = false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('A valid Home Assistant HTTP or HTTPS base URL is required.');
        }

        $path = rtrim($parts['path'] ?? '', '/');
        if (! str_ends_with($path, '/api')) {
            $path .= '/api';
        }
        $port = $parts['port'] ?? null;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $userInfo = isset($parts['user'])
            ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@'
            : '';

        return $scheme.'://'.$userInfo.strtolower($parts['host'])
            .($port !== null && $port !== $defaultPort ? ':'.$port : '').$path.'/';
    }
}
