<?php

return [
    'local_bypass' => (bool) env('API_DOCS_LOCAL_BYPASS', false),

    'groups' => [
        'auth' => [
            'label' => 'Auth',
            'description' => 'Authentication and current-user identity endpoints.',
            'label_key' => 'api-docs.groups.auth.label',
            'description_key' => 'api-docs.groups.auth.description',
            'paths' => [
                '/api/v1/auth/*',
            ],
            'public' => true,
        ],
        'users_rbac' => [
            'label' => 'Users & RBAC',
            'description' => 'Users, roles and permissions management endpoints.',
            'label_key' => 'api-docs.groups.users_rbac.label',
            'description_key' => 'api-docs.groups.users_rbac.description',
            'paths' => [
                '/api/v1/users*',
                '/api/v1/roles*',
                '/api/v1/permissions*',
            ],
            'permissions_any' => [
                'users.view',
                'roles.view',
                'permissions.view',
            ],
        ],
        'dashboard_stats' => [
            'label' => 'Dashboard & Stats',
            'description' => 'Dashboard runtime, meta, stats and activity endpoints.',
            'label_key' => 'api-docs.groups.dashboard_stats.label',
            'description_key' => 'api-docs.groups.dashboard_stats.description',
            'paths' => [
                '/api/v1/stats',
                '/api/v1/activity*',
                '/api/v1/meta*',
            ],
            'permissions_any' => [
                'dashboard.view',
                'activity.view',
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Notifications and preferences endpoints.',
            'label_key' => 'api-docs.groups.notifications.label',
            'description_key' => 'api-docs.groups.notifications.description',
            'paths' => [
                '/api/v1/notifications*',
            ],
            'permissions_any' => [
                'notifications.view',
                'notifications.delete',
            ],
        ],
        'chat' => [
            'label' => 'Chat',
            'description' => 'Conversations, messages, participants, typing, presence and attachments.',
            'label_key' => 'api-docs.groups.chat.label',
            'description_key' => 'api-docs.groups.chat.description',
            'paths' => [
                '/api/v1/chat/conversations*',
                '/api/v1/chat/messages*',
                '/api/v1/chat/attachments*',
                '/api/v1/chat/devices*',
            ],
            'permissions_any' => [
                'chat.view',
                'chat.conversations.view',
                'chat.send',
                'chat.admin.view',
            ],
        ],
        'webhooks' => [
            'label' => 'Webhooks',
            'description' => 'Webhook endpoint management and webhook delivery inspection endpoints.',
            'label_key' => 'api-docs.groups.webhooks.label',
            'description_key' => 'api-docs.groups.webhooks.description',
            'paths' => [
                '/api/v1/chat/webhook-endpoints*',
                '/api/v1/chat/conversations/*/webhook-deliveries',
            ],
            'permissions_any' => [
                'chat.webhooks.view',
                'chat.webhooks.manage',
                'chat.admin.view_metadata',
            ],
        ],
        'external_api' => [
            'label' => 'External API',
            'description' => 'External chat message sending and incoming webhooks.',
            'label_key' => 'api-docs.groups.external_api.label',
            'description_key' => 'api-docs.groups.external_api.description',
            'paths' => [
                '/api/v1/chat/external/messages',
                '/api/v1/chat/external/webhooks/*',
            ],
            'permissions_any' => [
                'chat.external_api.use',
                'chat.external_api.manage',
            ],
            'external_scopes_any' => [
                'chat.external.messages.send',
                'chat.external.webhooks.manage',
                'chat.external.webhooks.view',
                'chat.external.webhooks.deliveries.view',
            ],
        ],
    ],
];
