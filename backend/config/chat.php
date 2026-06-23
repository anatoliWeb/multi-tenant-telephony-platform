<?php

return [
    'attachments' => [
        'disk' => env('CHAT_ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => (int) env('CHAT_ATTACHMENTS_MAX_SIZE_KB', 10240),
        'allowed_mimes' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'audio/mp4',
        ],
        // Placeholder strategy only for this phase (no real scanner integration).
        'virus_scan_enabled' => (bool) env('CHAT_ATTACHMENTS_VIRUS_SCAN_ENABLED', false),
    ],
    'typing' => [
        // Throttle start-typing broadcast to reduce event noise.
        'throttle_seconds' => (int) env('CHAT_TYPING_THROTTLE_SECONDS', 2),
    ],
    'presence' => [
        // Devices older than this threshold are considered stale.
        'stale_after_seconds' => (int) env('CHAT_PRESENCE_STALE_AFTER_SECONDS', 120),
    ],
    'message_sending_rate_limit' => [
        'enabled' => (bool) env('CHAT_MESSAGE_SEND_RATE_LIMIT_ENABLED', true),
        'max_attempts' => (int) env('CHAT_MESSAGE_SEND_RATE_LIMIT_MAX_ATTEMPTS', 30),
        'decay_seconds' => (int) env('CHAT_MESSAGE_SEND_RATE_LIMIT_DECAY_SECONDS', 60),
    ],
    'external_api' => [
        'token_prefix' => env('CHAT_EXTERNAL_API_TOKEN_PREFIX', 'chat_ext_'),
        'token_hash_algo' => 'sha256',
        'scopes' => [
            'allowed' => [
                'chat.external.messages.send',
                'chat.external.webhooks.manage',
                'chat.external.webhooks.view',
                'chat.external.webhooks.deliveries.view',
                'chat.external.logs.view',
            ],
            'default' => [
                ...array_values(array_filter(array_map(
                    static fn (string $scope): string => trim($scope),
                    explode(',', (string) env('CHAT_EXTERNAL_API_TOKEN_SCOPES_DEFAULT', 'chat.external.messages.send'))
                ))),
            ],
        ],
        'rate_limit' => [
            'enabled' => (bool) env('CHAT_EXTERNAL_API_RATE_LIMIT_ENABLED', true),
            'max_attempts' => (int) env('CHAT_EXTERNAL_API_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_seconds' => (int) env('CHAT_EXTERNAL_API_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
    ],
    'webhooks' => [
        'signing_algo' => 'sha256',
        'signature_header' => 'X-Chat-Signature',
        'timestamp_header' => 'X-Chat-Timestamp',
        'tolerance_seconds' => (int) env('CHAT_WEBHOOK_SIGNATURE_TOLERANCE_SECONDS', 300),
        'replay_protection' => [
            'enabled' => (bool) env('CHAT_WEBHOOK_REPLAY_PROTECTION_ENABLED', true),
            'ttl_seconds' => (int) env('CHAT_WEBHOOK_REPLAY_PROTECTION_TTL_SECONDS', 300),
        ],
        'retry_backoff_seconds' => [60, 300, 900, 3600],
        'max_attempts' => (int) env('CHAT_WEBHOOK_MAX_ATTEMPTS', 5),
        'secret_rotation_grace_seconds' => (int) env('CHAT_WEBHOOK_SECRET_ROTATION_GRACE_SECONDS', 86400),
        'endpoint_management_rate_limit' => [
            'max_attempts' => (int) env('CHAT_WEBHOOK_MANAGEMENT_RATE_LIMIT_MAX_ATTEMPTS', 30),
            'decay_seconds' => (int) env('CHAT_WEBHOOK_MANAGEMENT_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
    ],
    'suspicious_activity' => [
        'enabled' => (bool) env('CHAT_SUSPICIOUS_ACTIVITY_ENABLED', true),
        'log_only' => (bool) env('CHAT_SUSPICIOUS_ACTIVITY_LOG_ONLY', true),
        'max_message_length' => (int) env('CHAT_SUSPICIOUS_ACTIVITY_MAX_MESSAGE_LENGTH', 5000),
        'max_attachments' => (int) env('CHAT_SUSPICIOUS_ACTIVITY_MAX_ATTACHMENTS', 10),
    ],
    'activity_integration' => [
        'enabled' => (bool) env('CHAT_ACTIVITY_INTEGRATION_ENABLED', true),
        'actions' => [
            'message.deleted',
            'message.admin_reply_created',
            'conversation.closed',
            'conversation.archived',
            'participant.blocked',
            'participant.unblocked',
            'participant.read_only',
            'participant.hidden',
            'attachment.deleted',
            'webhook.delivery.failed',
            'suspicious.message_activity',
            'history.imported',
        ],
    ],
];
