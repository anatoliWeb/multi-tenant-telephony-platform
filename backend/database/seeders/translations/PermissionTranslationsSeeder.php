<?php

namespace Database\Seeders\translations;

/**
 * Seeds permission localization keys.
 *
 * TECHNICAL IDENTIFIER POLICY:
 * `permissions.name` remains immutable (e.g. users.create).
 * This seeder adds multilingual labels/descriptions only.
 */
class PermissionTranslationsSeeder extends BaseTranslationsSeeder
{
    public function run(): void
    {
        $labels = [
            'access_admin' => [
                'en' => 'Access Admin Panel',
                'uk' => 'Доступ до адмін-панелі',
                'de' => 'Zugriff auf Admin-Panel'
            ],

            'users.view' => [
                'en' => 'View users',
                'uk' => 'Перегляд користувачів',
                'de' => 'Benutzer anzeigen'
            ],

            'users.create' => [
                'en' => 'Create users',
                'uk' => 'Створення користувачів',
                'de' => 'Benutzer erstellen'
            ],

            'users.edit' => [
                'en' => 'Edit users',
                'uk' => 'Редагування користувачів',
                'de' => 'Benutzer bearbeiten'
            ],

            'users.delete' => [
                'en' => 'Delete users',
                'uk' => 'Видалення користувачів',
                'de' => 'Benutzer löschen'
            ],

            'roles.view' => [
                'en' => 'View roles',
                'uk' => 'Перегляд ролей',
                'de' => 'Rollen anzeigen'
            ],

            'roles.create' => [
                'en' => 'Create roles',
                'uk' => 'Створення ролей',
                'de' => 'Rollen erstellen'
            ],

            'roles.edit' => [
                'en' => 'Edit roles',
                'uk' => 'Редагування ролей',
                'de' => 'Rollen bearbeiten'
            ],

            'roles.delete' => [
                'en' => 'Delete roles',
                'uk' => 'Видалення ролей',
                'de' => 'Rollen löschen'
            ],

            'roles.assign_permissions' => [
                'en' => 'Assign role permissions',
                'uk' => 'Призначення дозволів ролям',
                'de' => 'Rollenberechtigungen zuweisen',
            ],
            'permissions.view' => [
                'en' => 'View permissions',
                'uk' => 'Перегляд дозволів',
                'de' => 'Berechtigungen anzeigen'
            ],

            'permissions.create' => [
                'en' => 'Create permissions',
                'uk' => 'Створення дозволів',
                'de' => 'Berechtigungen erstellen'
            ],

            'permissions.edit' => [
                'en' => 'Edit permissions',
                'uk' => 'Редагування дозволів',
                'de' => 'Berechtigungen bearbeiten'
            ],

            'permissions.delete' => [
                'en' => 'Delete permissions',
                'uk' => 'Видалення дозволів',
                'de' => 'Berechtigungen löschen'
            ],

            'tokens.view' => [
                'en' => 'View tokens',
                'uk' => 'Перегляд токенів',
                'de' => 'Token anzeigen'
            ],

            'tokens.create' => [
                'en' => 'Create tokens',
                'uk' => 'Створення токенів',
                'de' => 'Token erstellen'
            ],

            'tokens.delete' => [
                'en' => 'Delete tokens',
                'uk' => 'Видалення токенів',
                'de' => 'Token löschen'
            ],

            'tokens.edit' => [
                'en' => 'Edit tokens',
                'uk' => 'Редагування токенів',
                'de' => 'Token bearbeiten',
            ],

            'settings.view' => [
                'en' => 'View settings',
                'uk' => 'Перегляд налаштувань',
                'de' => 'Einstellungen anzeigen',
            ],

            'settings.edit' => [
                'en' => 'Edit settings',
                'uk' => 'Редагування налаштувань',
                'de' => 'Einstellungen bearbeiten',
            ],

            'activity.view' => [
                'en' => 'View activity logs',
                'uk' => 'Перегляд журналу активності',
                'de' => 'Aktivitätsprotokolle anzeigen',
            ],
            'system.monitoring' => [
                'en' => 'Access system monitoring',
                'uk' => 'Доступ до системного моніторингу',
                'de' => 'Systemmonitoring aufrufen',
            ],

            'translations.view' => [
                'en' => 'View translations',
                'uk' => 'Перегляд перекладів',
                'de' => 'Übersetzungen anzeigen',
            ],

            'translations.create' => [
                'en' => 'Create translations',
                'uk' => 'Створення перекладів',
                'de' => 'Übersetzungen erstellen',
            ],

            'translations.edit' => [
                'en' => 'Edit translations',
                'uk' => 'Редагування перекладів',
                'de' => 'Übersetzungen bearbeiten',
            ],

            'translations.delete' => [
                'en' => 'Delete translations',
                'uk' => 'Видалення перекладів',
                'de' => 'Übersetzungen löschen',
            ],
            'dashboard.view' => [
                'en' => 'View dashboard',
                'uk' => 'Перегляд панелі керування',
                'de' => 'Dashboard anzeigen',
            ],

            'notifications.view' => [
                'en' => 'View notifications',
                'uk' => 'Перегляд сповіщень',
                'de' => 'Benachrichtigungen anzeigen',
            ],

            'notifications.create' => [
                'en' => 'Create notifications',
                'uk' => 'Створення сповіщень',
                'de' => 'Benachrichtigungen erstellen',
            ],

            'notifications.delete' => [
                'en' => 'Delete notifications',
                'uk' => 'Видалення сповіщень',
                'de' => 'Benachrichtigungen löschen',
            ],
            'tenants.view' => [
                'en' => 'View tenants',
                'uk' => 'РџРµСЂРµРіР»СЏРґ С‚РµРЅР°РЅС‚С–РІ',
                'de' => 'Mandanten anzeigen',
            ],
            'api.docs.view' => [
                'en' => 'View API documentation',
                'uk' => 'Перегляд API документації',
                'de' => 'API-Dokumentation anzeigen',
            ],
            'api.docs.view.full' => [
                'en' => 'View full API documentation',
                'uk' => 'Перегляд повної API документації',
                'de' => 'Vollständige API-Dokumentation anzeigen',
            ],
        ];

        $descriptions = [
            'access_admin' => [
                'en' => 'Allows opening the administrative application shell.',
                'uk' => 'Дозволяє відкривати адміністративну оболонку застосунку.',
                'de' => 'Erlaubt das Öffnen der administrativen Anwendungsshell.',
            ],
            'users.view' => [
                'en' => 'Allows viewing user records and profile metadata.',
                'uk' => 'Дозволяє переглядати записи користувачів і метадані профілю.',
                'de' => 'Erlaubt das Anzeigen von Benutzerdatensätzen und Profilmetadaten.',
            ],
            'users.create' => [
                'en' => 'Allows creating new user accounts.',
                'uk' => 'Дозволяє створювати нові облікові записи користувачів.',
                'de' => 'Erlaubt das Erstellen neuer Benutzerkonten.',
            ],
            'users.edit' => [
                'en' => 'Allows updating existing user records.',
                'uk' => 'Дозволяє оновлювати існуючі записи користувачів.',
                'de' => 'Erlaubt das Aktualisieren bestehender Benutzerdatensätze.',
            ],
            'users.delete' => [
                'en' => 'Allows deleting user accounts from the platform.',
                'uk' => 'Дозволяє видаляти облікові записи користувачів з платформи.',
                'de' => 'Erlaubt das Löschen von Benutzerkonten von der Plattform.',
            ],
            'roles.view' => [
                'en' => 'Allows viewing RBAC role definitions.',
                'uk' => 'Дозволяє переглядати визначення ролей RBAC.',
                'de' => 'Erlaubt das Anzeigen von RBAC-Rollendefinitionen.',
            ],
            'roles.create' => [
                'en' => 'Allows creating new RBAC roles.',
                'uk' => 'Дозволяє створювати нові ролі RBAC.',
                'de' => 'Erlaubt das Erstellen neuer RBAC-Rollen.',
            ],
            'roles.edit' => [
                'en' => 'Allows editing role metadata and permission mapping.',
                'uk' => 'Дозволяє редагувати метадані ролей і зв’язки дозволів.',
                'de' => 'Erlaubt das Bearbeiten von Rollenmetadaten und Berechtigungszuordnungen.',
            ],
            'roles.delete' => [
                'en' => 'Allows deleting RBAC roles when safe constraints permit.',
                'uk' => 'Дозволяє видаляти ролі RBAC, коли це безпечно за обмеженнями.',
                'de' => 'Erlaubt das Löschen von RBAC-Rollen, sofern sichere Einschränkungen erfüllt sind.',
            ],
            'roles.assign_permissions' => [
                'en' => 'Allows assigning permissions to RBAC roles.',
                'uk' => 'Дозволяє призначати дозволи ролям RBAC.',
                'de' => 'Erlaubt das Zuweisen von Berechtigungen zu RBAC-Rollen.',
            ],
            'permissions.view' => [
                'en' => 'Allows viewing permission catalog entries.',
                'uk' => 'Дозволяє переглядати елементи каталогу дозволів.',
                'de' => 'Erlaubt das Anzeigen von Einträgen im Berechtigungskatalog.',
            ],
            'permissions.create' => [
                'en' => 'Allows creating new permission definitions.',
                'uk' => 'Дозволяє створювати нові визначення дозволів.',
                'de' => 'Erlaubt das Erstellen neuer Berechtigungsdefinitionen.',
            ],
            'permissions.edit' => [
                'en' => 'Allows editing existing permission definitions.',
                'uk' => 'Дозволяє редагувати існуючі визначення дозволів.',
                'de' => 'Erlaubt das Bearbeiten bestehender Berechtigungsdefinitionen.',
            ],
            'permissions.delete' => [
                'en' => 'Allows deleting permission definitions.',
                'uk' => 'Дозволяє видаляти визначення дозволів.',
                'de' => 'Erlaubt das Löschen von Berechtigungsdefinitionen.',
            ],
            'tokens.view' => [
                'en' => 'Allows viewing API token inventory.',
                'uk' => 'Дозволяє переглядати реєстр API-токенів.',
                'de' => 'Erlaubt das Anzeigen des API-Token-Bestands.',
            ],
            'tokens.create' => [
                'en' => 'Allows creating new API tokens.',
                'uk' => 'Дозволяє створювати нові API-токени.',
                'de' => 'Erlaubt das Erstellen neuer API-Token.',
            ],
            'tokens.delete' => [
                'en' => 'Allows deleting existing API tokens.',
                'uk' => 'Дозволяє видаляти існуючі API-токени.',
                'de' => 'Erlaubt das Löschen vorhandener API-Token.',
            ],
            'tokens.edit' => [
                'en' => 'Allows editing existing API tokens.',
                'uk' => 'Дозволяє редагувати існуючі API-токени.',
                'de' => 'Erlaubt das Bearbeiten vorhandener API-Token.',
            ],
            'dashboard.view' => [
                'en' => 'Allows viewing dashboard widgets and operational statistics.',
                'uk' => 'Дозволяє переглядати віджети панелі керування та операційну статистику.',
                'de' => 'Erlaubt das Anzeigen von Dashboard-Widgets und Betriebsstatistiken.',
            ],
            'settings.view' => [
                'en' => 'Allows viewing system configuration settings.',
                'uk' => 'Дозволяє переглядати системні налаштування.',
                'de' => 'Erlaubt das Anzeigen von Systemeinstellungen.',
            ],

            'settings.edit' => [
                'en' => 'Allows updating platform configuration settings.',
                'uk' => 'Дозволяє оновлювати конфігурацію платформи.',
                'de' => 'Erlaubt das Aktualisieren von Plattformkonfigurationen.',
            ],

            'activity.view' => [
                'en' => 'Allows viewing audit and activity logs.',
                'uk' => 'Дозволяє переглядати журнали аудиту та активності.',
                'de' => 'Erlaubt das Anzeigen von Audit- und Aktivitätsprotokollen.',
            ],
            'system.monitoring' => [
                'en' => 'Allows access to queue and infrastructure monitoring dashboards.',
                'uk' => 'Дозволяє доступ до панелей моніторингу черг та інфраструктури.',
                'de' => 'Erlaubt den Zugriff auf Dashboards für Queue- und Infrastruktur-Monitoring.',
            ],

            'translations.view' => [
                'en' => 'Allows viewing runtime translations.',
                'uk' => 'Дозволяє переглядати runtime-переклади.',
                'de' => 'Erlaubt das Anzeigen von Runtime-Übersetzungen.',
            ],

            'translations.create' => [
                'en' => 'Allows creating new translation keys and locale values.',
                'uk' => 'Дозволяє створювати нові ключі перекладів і значення локалей.',
                'de' => 'Erlaubt das Erstellen neuer Übersetzungsschlüssel und Sprachwerte.',
            ],

            'translations.edit' => [
                'en' => 'Allows editing existing translations.',
                'uk' => 'Дозволяє редагувати існуючі переклади.',
                'de' => 'Erlaubt das Bearbeiten bestehender Übersetzungen.',
            ],

            'translations.delete' => [
                'en' => 'Allows deleting translation keys and locale entries.',
                'uk' => 'Дозволяє видаляти ключі перекладів і записи локалей.',
                'de' => 'Erlaubt das Löschen von Übersetzungsschlüsseln und Spracheinträgen.',
            ],

            'notifications.view' => [
                'en' => 'Allows viewing user and system notifications.',
                'uk' => 'Дозволяє переглядати користувацькі та системні сповіщення.',
                'de' => 'Erlaubt das Anzeigen von Benutzer- und Systembenachrichtigungen.',
            ],

            'notifications.create' => [
                'en' => 'Allows creating system notifications for users.',
                'uk' => 'Дозволяє створювати системні сповіщення для користувачів.',
                'de' => 'Erlaubt das Erstellen von Systembenachrichtigungen für Benutzer.',
            ],

            'notifications.delete' => [
                'en' => 'Allows deleting user and system notifications.',
                'uk' => 'Дозволяє видаляти користувацькі та системні сповіщення.',
                'de' => 'Erlaubt das Löschen von Benutzer- und Systembenachrichtigungen.',
            ],
            'tenants.view' => [
                'en' => 'Allows viewing the active tenant catalog for support context selection.',
                'uk' => 'Р”РѕР·РІРѕР»СЏС” РїРµСЂРµРіР»СЏРґР°С‚Рё РєР°С‚Р°Р»РѕРі Р°РєС‚РёРІРЅРёС… С‚РµРЅР°РЅС‚С–РІ РґР»СЏ РІРёР±РѕСЂСѓ support-РєРѕРЅС‚РµРєСЃС‚Сѓ.',
                'de' => 'Erlaubt das Anzeigen des aktiven Mandantenkatalogs fГјr die Auswahl des Supportkontexts.',
            ],
            'api.docs.view' => [
                'en' => 'Allows access to protected OpenAPI and Swagger documentation.',
                'uk' => 'Дозволяє доступ до захищеної OpenAPI та Swagger документації.',
                'de' => 'Erlaubt den Zugriff auf geschützte OpenAPI- und Swagger-Dokumentation.',
            ],
            'api.docs.view.full' => [
                'en' => 'Allows viewing all OpenAPI groups regardless of endpoint-specific permissions.',
                'uk' => 'Дозволяє переглядати всі групи OpenAPI незалежно від дозволів конкретних ендпоінтів.',
                'de' => 'Erlaubt das Anzeigen aller OpenAPI-Gruppen unabhängig von endpointspezifischen Berechtigungen.',
            ],
        ];

        $rows = [];

        foreach ($labels as $key => $translations) {
            foreach ($translations as $locale => $value) {
                $rows[] = [
                    'locale' => $locale,
                    'group' => 'permissions',
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        foreach ($descriptions as $key => $translations) {
            foreach ($translations as $locale => $value) {
                $rows[] = [
                    'locale' => $locale,
                    'group' => 'permission_descriptions',
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        $this->seedTranslations($rows);
    }
}
