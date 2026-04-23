<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'OPTIONS'],

    'allowed_origins' => [
        'capacitor://localhost',
        'http://localhost',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Origin',
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
        'Range',
    ],

    'exposed_headers' => [
        'Content-Disposition',
        'Content-Length',
        'Content-Range',
        'Accept-Ranges',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
