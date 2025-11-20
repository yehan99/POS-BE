<?php

return [
    'secret' => env('JWT_SECRET'),

    'algo' => env('JWT_ALGO', 'HS256'),

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1_209_600),

    'idle_timeout' => (int) env('JWT_IDLE_TIMEOUT', 1_800),

    'idle_warning' => (int) env('JWT_IDLE_WARNING', 60),
];
