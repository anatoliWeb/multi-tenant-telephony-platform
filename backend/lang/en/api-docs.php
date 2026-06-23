<?php

return [
    'title' => 'API Documentation',
    'subtitle' => 'Available API groups for your account.',
    'open_swagger' => 'Open Swagger UI',
    'open_json' => 'Open OpenAPI JSON',
    'open_filtered_spec' => 'Open filtered API spec',
    'raw_swagger_access_note' => 'Raw Swagger UI and raw OpenAPI JSON are available only for full-access users.',
    'access_states' => [
        'full' => 'Full access',
        'limited' => 'Limited access',
        'none' => 'No endpoint access',
    ],
    'full_access_notice' => 'Full documentation access enabled',
    'empty_title' => 'No available API groups',
    'empty_description' => 'You have access to API documentation, but no API groups are available for your current permissions.',
    'groups' => [
        'auth' => [
            'label' => 'Auth',
            'description' => 'Authentication and current-user identity endpoints.',
        ],
        'users_rbac' => [
            'label' => 'Users & RBAC',
            'description' => 'Users, roles and permissions management endpoints.',
        ],
        'dashboard_stats' => [
            'label' => 'Dashboard & Stats',
            'description' => 'Dashboard runtime, meta, stats and activity endpoints.',
        ],
        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Notifications and preferences endpoints.',
        ],
        'chat' => [
            'label' => 'Chat',
            'description' => 'Conversations, messages, participants, typing, presence and attachments.',
        ],
        'webhooks' => [
            'label' => 'Webhooks',
            'description' => 'Webhook endpoint management and webhook delivery inspection endpoints.',
        ],
        'external_api' => [
            'label' => 'External API',
            'description' => 'External chat message sending and incoming webhooks.',
        ],
    ],
];
