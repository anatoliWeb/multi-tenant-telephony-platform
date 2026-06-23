<?php

namespace Database\Seeders\translations;

/**
 * Seeds dynamic dashboard translations.
 *
 * WHY:
 * Dashboard widgets, metrics, and runtime blocks often evolve dynamically
 * and therefore benefit from database-driven localization.
 *
 * This architecture prepares the platform for:
 * - customizable dashboards
 * - widget-based SaaS admin systems
 * - realtime metric labeling
 * - tenant-specific dashboards
 * - future drag/drop widget builders
 *
 * IMPORTANT:
 * Static UI shell texts should still remain inside Vue/Laravel translation files.
 *
 * This seeder focuses on:
 * - dashboard entities
 * - runtime widgets
 * - analytics labels
 * - metric labels
 */
class DashboardTranslationsSeeder extends BaseTranslationsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedTranslations([

            /*
            |--------------------------------------------------------------------------
            | Module Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.module.title',
                'value' => 'Dashboard',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.module.title',
                'value' => 'Дашборд',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.module.title',
                'value' => 'Dashboard',
            ],

            /*
            |--------------------------------------------------------------------------
            | Widget Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.users',
                'value' => 'Users',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.users',
                'value' => 'Користувачі',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.users',
                'value' => 'Benutzer',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.roles',
                'value' => 'Roles',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.roles',
                'value' => 'Ролі',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.roles',
                'value' => 'Rollen',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.permissions',
                'value' => 'Permissions',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.permissions',
                'value' => 'Дозволи',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.permissions',
                'value' => 'Berechtigungen',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.tokens',
                'value' => 'API Tokens',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.tokens',
                'value' => 'API токени',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.tokens',
                'value' => 'API-Token',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.activity',
                'value' => 'Recent Activity',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.activity',
                'value' => 'Остання активність',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.widgets.activity',
                'value' => 'Letzte Aktivität',
            ],

            /*
            |--------------------------------------------------------------------------
            | Metric Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.total_users',
                'value' => 'Total Users',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.total_users',
                'value' => 'Всього користувачів',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.total_users',
                'value' => 'Gesamtbenutzer',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.active_sessions',
                'value' => 'Active Sessions',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.active_sessions',
                'value' => 'Активні сесії',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.active_sessions',
                'value' => 'Aktive Sitzungen',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.api_requests',
                'value' => 'API Requests',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.api_requests',
                'value' => 'API запити',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.api_requests',
                'value' => 'API-Anfragen',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.realtime_connections',
                'value' => 'Realtime Connections',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.realtime_connections',
                'value' => 'Realtime підключення',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.metrics.realtime_connections',
                'value' => 'Realtime-Verbindungen',
            ],

            /*
            |--------------------------------------------------------------------------
            | Realtime Status
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.realtime.online',
                'value' => 'Online',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.realtime.online',
                'value' => 'Онлайн',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.realtime.online',
                'value' => 'Online',
            ],

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.realtime.offline',
                'value' => 'Offline',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.realtime.offline',
                'value' => 'Офлайн',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.realtime.offline',
                'value' => 'Offline',
            ],

            /*
            |--------------------------------------------------------------------------
            | Empty States
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'dashboard',
                'key' => 'dashboard.empty.no_data',
                'value' => 'No data available',
            ],

            [
                'locale' => 'uk',
                'group' => 'dashboard',
                'key' => 'dashboard.empty.no_data',
                'value' => 'Дані відсутні',
            ],

            [
                'locale' => 'de',
                'group' => 'dashboard',
                'key' => 'dashboard.empty.no_data',
                'value' => 'Keine Daten verfügbar',
            ],
        ]);
    }
}
