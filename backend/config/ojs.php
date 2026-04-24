<?php

return [
    'http' => [
        'timeout' => (int) env('OJS_HTTP_TIMEOUT', 20),
        'connect_timeout' => (int) env('OJS_HTTP_CONNECT_TIMEOUT', 8),
        'retry_times' => (int) env('OJS_HTTP_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('OJS_HTTP_RETRY_SLEEP_MS', 200),
        'cache_ttl_seconds' => (int) env('OJS_CACHE_TTL_SECONDS', 300),
    ],

    'sources' => array_values(array_filter([
        [
            'slug' => env('OJS_SOURCE_1_SLUG', 'investigacion'),
            'name' => env('OJS_SOURCE_1_NAME', 'Investigacion UNITEPC'),
            'driver' => env('OJS_SOURCE_1_DRIVER', 'public_ojs_34'),
            'base_url' => env('OJS_SOURCE_1_BASE_URL', 'https://investigacion.unitepc.edu.bo/revista'),
            'site_url' => env('OJS_SOURCE_1_SITE_URL', 'https://investigacion.unitepc.edu.bo/revista/'),
            'api_base_url' => env('OJS_SOURCE_1_API_BASE_URL', 'https://investigacion.unitepc.edu.bo/revista/index.php/_/api/v1'),
            'enabled' => filter_var(env('OJS_SOURCE_1_ENABLED', true), FILTER_VALIDATE_BOOL),
            'token' => env('OJS_SOURCE_1_TOKEN'),
        ],
        [
            'slug' => env('OJS_SOURCE_2_SLUG', 'g-news'),
            'name' => env('OJS_SOURCE_2_NAME', 'G-News UNITEPC'),
            'driver' => env('OJS_SOURCE_2_DRIVER', 'public_ojs_34'),
            'base_url' => env('OJS_SOURCE_2_BASE_URL', 'https://g-news.unitepc.edu.bo/revista'),
            'api_base_url' => env('OJS_SOURCE_2_API_BASE_URL'),
            'journal_url' => env('OJS_SOURCE_2_JOURNAL_URL', 'https://g-news.unitepc.edu.bo/revista/index.php/revista'),
            'archive_url' => env('OJS_SOURCE_2_ARCHIVE_URL', 'https://g-news.unitepc.edu.bo/revista/index.php/revista/issue/archive'),
            'oai_base_url' => env('OJS_SOURCE_2_OAI_BASE_URL', 'https://g-news.unitepc.edu.bo/revista/index.php/revista/oai'),
            'enabled' => filter_var(env('OJS_SOURCE_2_ENABLED', true), FILTER_VALIDATE_BOOL),
            'token' => env('OJS_SOURCE_2_TOKEN'),
        ],
    ], static fn (array $source) => filled($source['slug']) && filled($source['base_url']))),
];
