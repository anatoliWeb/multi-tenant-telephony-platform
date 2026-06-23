<?php

return [
    'title' => 'API Документація',
    'subtitle' => 'Доступні API-групи для вашого облікового запису.',
    'open_swagger' => 'Відкрити Swagger UI',
    'open_json' => 'Відкрити OpenAPI JSON',
    'open_filtered_spec' => 'Відкрити відфільтрований API spec',
    'access_states' => [
        'full' => 'Повний доступ',
        'limited' => 'Обмежений доступ',
        'none' => 'Немає доступу до ендпоінтів',
    ],
    'full_access_notice' => 'Увімкнено повний доступ до документації',
    'empty_title' => 'Немає доступних API-груп',
    'empty_description' => 'У вас є доступ до API документації, але для ваших поточних дозволів немає доступних API-груп.',
    'groups' => [
        'auth' => [
            'label' => 'Auth',
            'description' => 'Ендпоінти автентифікації та поточного користувача.',
        ],
        'users_rbac' => [
            'label' => 'Користувачі та RBAC',
            'description' => 'Ендпоінти керування користувачами, ролями та дозволами.',
        ],
        'dashboard_stats' => [
            'label' => 'Dashboard та Stats',
            'description' => 'Ендпоінти dashboard runtime, meta, stats та activity.',
        ],
        'notifications' => [
            'label' => 'Сповіщення',
            'description' => 'Ендпоінти сповіщень та налаштувань сповіщень.',
        ],
        'chat' => [
            'label' => 'Чат',
            'description' => 'Ендпоінти розмов, повідомлень, учасників, typing, presence та вкладень.',
        ],
        'webhooks' => [
            'label' => 'Webhook-и',
            'description' => 'Ендпоінти керування webhook-ами та перегляду доставок webhook-ів.',
        ],
        'external_api' => [
            'label' => 'Зовнішнє API',
            'description' => 'Зовнішнє API для відправки chat повідомлень та вхідних webhook-ів.',
        ],
    ],
];
