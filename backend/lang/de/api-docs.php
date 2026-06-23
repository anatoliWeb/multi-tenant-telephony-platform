<?php

return [
    'title' => 'API-Dokumentation',
    'subtitle' => 'Verfügbare API-Gruppen für Ihr Konto.',
    'open_swagger' => 'Swagger UI öffnen',
    'open_json' => 'OpenAPI JSON öffnen',
    'open_filtered_spec' => 'Gefilterte API-Spezifikation öffnen',
    'access_states' => [
        'full' => 'Vollzugriff',
        'limited' => 'Eingeschränkter Zugriff',
        'none' => 'Kein Endpoint-Zugriff',
    ],
    'full_access_notice' => 'Vollständiger Dokumentationszugriff aktiviert',
    'empty_title' => 'Keine verfügbaren API-Gruppen',
    'empty_description' => 'Sie haben Zugriff auf die API-Dokumentation, aber für Ihre aktuellen Berechtigungen sind keine API-Gruppen verfügbar.',
    'groups' => [
        'auth' => [
            'label' => 'Auth',
            'description' => 'Authentifizierungs- und Current-User-Endpunkte.',
        ],
        'users_rbac' => [
            'label' => 'Benutzer & RBAC',
            'description' => 'Endpunkte zur Verwaltung von Benutzern, Rollen und Berechtigungen.',
        ],
        'dashboard_stats' => [
            'label' => 'Dashboard & Statistiken',
            'description' => 'Endpunkte für Dashboard-Runtime, Meta, Statistiken und Aktivitäten.',
        ],
        'notifications' => [
            'label' => 'Benachrichtigungen',
            'description' => 'Benachrichtigungs- und Präferenz-Endpunkte.',
        ],
        'chat' => [
            'label' => 'Chat',
            'description' => 'Endpunkte für Konversationen, Nachrichten, Teilnehmer, Typing, Presence und Anhänge.',
        ],
        'webhooks' => [
            'label' => 'Webhooks',
            'description' => 'Endpunkte für Webhook-Verwaltung und Webhook-Lieferstatus.',
        ],
        'external_api' => [
            'label' => 'Externe API',
            'description' => 'Externe Chat-Nachrichtenübertragung und eingehende Webhooks.',
        ],
    ],
];
