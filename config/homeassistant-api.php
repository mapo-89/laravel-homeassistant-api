<?php

return [
    'url' => env('HA_URL', 'http://homeassistant.local:8123/api/'),
    'token' => env('HA_TOKEN', null),

    // REST connection and response timeouts, in seconds.
    'connect_timeout' => 3,
    'timeout' => 8,

    // Cache state lists for five seconds, separately per URL and token.
    'cache_responses' => true,
];
