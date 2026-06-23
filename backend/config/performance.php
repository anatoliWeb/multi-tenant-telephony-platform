<?php

return [
    'cache' => [
        'enabled' => (bool) env('PERFORMANCE_CACHE_ENABLED', true),
        'store' => env('PERFORMANCE_CACHE_STORE'),

        'default_ttl' => (int) env('PERFORMANCE_CACHE_DEFAULT_TTL', 300),
        'meta_ttl' => (int) env('PERFORMANCE_CACHE_META_TTL', 300),
        'rbac_ttl' => (int) env('PERFORMANCE_CACHE_RBAC_TTL', 600),
        'stats_ttl' => (int) env('PERFORMANCE_CACHE_STATS_TTL', 60),
        'api_docs_ttl' => (int) env('PERFORMANCE_CACHE_API_DOCS_TTL', 600),
    ],
];

