<?php

return [
    'version' => '1.0.0',
    'cache_ttl' => (int) env('DIRECTORY_CACHE_TTL', 300),
    'token_expiration' => (int) env('TOKEN_EXPIRATION_MINUTES', 480),
    'public_rate_limit' => (int) env('PUBLIC_RATE_LIMIT', 60),
    'authenticated_rate_limit' => (int) env('AUTHENTICATED_RATE_LIMIT', 120),
];
