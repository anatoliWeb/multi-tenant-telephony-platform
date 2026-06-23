<?php

return [
    'health' => [
        'enabled' => (bool) env('MONITORING_HEALTH_ENABLED', true),
        'protected_enabled' => (bool) env('MONITORING_HEALTH_PROTECTED_ENABLED', true),
        'timeout_ms' => (int) env('MONITORING_HEALTH_TIMEOUT_MS', 500),
        'expose_details' => (bool) env('MONITORING_HEALTH_EXPOSE_DETAILS', false),
        'checks' => [
            'database' => (bool) env('MONITORING_HEALTH_CHECK_DATABASE', true),
            'redis' => (bool) env('MONITORING_HEALTH_CHECK_REDIS', true),
            'cache' => (bool) env('MONITORING_HEALTH_CHECK_CACHE', true),
            'queue' => (bool) env('MONITORING_HEALTH_CHECK_QUEUE', true),
        ],
    ],
];

