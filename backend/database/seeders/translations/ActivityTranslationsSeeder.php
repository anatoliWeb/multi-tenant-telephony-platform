<?php

namespace Database\Seeders\translations;

/**
 * Seeds dynamic activity/audit translations.
 *
 * WHY:
 * Activity logs are runtime-generated entities and therefore require
 * database-driven localization support.
 *
 * This architecture prepares the platform for:
 * - multilingual audit systems
 * - realtime activity feeds
 * - enterprise monitoring
 * - security event tracking
 * - future SIEM integrations
 *
 * IMPORTANT:
 * Action keys themselves should remain stable and machine-readable.
 *
 * Translation values are only human-facing labels.
 */
class ActivityTranslationsSeeder extends BaseTranslationsSeeder
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
                'group' => 'activity',
                'key' => 'activity.module.title',
                'value' => 'Activity Logs',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.module.title',
                'value' => 'Журнал активності',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.module.title',
                'value' => 'Aktivitätsprotokoll',
            ],

            /*
            |--------------------------------------------------------------------------
            | Entity Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.entity.log',
                'value' => 'Activity Record',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.entity.log',
                'value' => 'Запис активності',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.entity.log',
                'value' => 'Aktivitätseintrag',
            ],

            /*
            |--------------------------------------------------------------------------
            | Table Columns
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.columns.user',
                'value' => 'User',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.columns.user',
                'value' => 'Користувач',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.columns.user',
                'value' => 'Benutzer',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.columns.action',
                'value' => 'Action',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.columns.action',
                'value' => 'Дія',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.columns.action',
                'value' => 'Aktion',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.columns.description',
                'value' => 'Description',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.columns.description',
                'value' => 'Опис',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.columns.description',
                'value' => 'Beschreibung',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.columns.created_at',
                'value' => 'Created',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.columns.created_at',
                'value' => 'Створено',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.columns.created_at',
                'value' => 'Erstellt',
            ],

            /*
            |--------------------------------------------------------------------------
            | Action Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.actions.created',
                'value' => 'Created',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.actions.created',
                'value' => 'Створено',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.actions.created',
                'value' => 'Erstellt',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.actions.updated',
                'value' => 'Updated',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.actions.updated',
                'value' => 'Оновлено',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.actions.updated',
                'value' => 'Aktualisiert',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.actions.deleted',
                'value' => 'Deleted',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.actions.deleted',
                'value' => 'Видалено',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.actions.deleted',
                'value' => 'Gelöscht',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.actions.login',
                'value' => 'Login',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.actions.login',
                'value' => 'Вхід',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.actions.login',
                'value' => 'Anmeldung',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.actions.logout',
                'value' => 'Logout',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.actions.logout',
                'value' => 'Вихід',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.actions.logout',
                'value' => 'Abmeldung',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.status.success',
                'value' => 'Success',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.status.success',
                'value' => 'Успішно',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.status.success',
                'value' => 'Erfolgreich',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.status.warning',
                'value' => 'Warning',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.status.warning',
                'value' => 'Попередження',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.status.warning',
                'value' => 'Warnung',
            ],

            [
                'locale' => 'en',
                'group' => 'activity',
                'key' => 'activity.status.error',
                'value' => 'Error',
            ],

            [
                'locale' => 'uk',
                'group' => 'activity',
                'key' => 'activity.status.error',
                'value' => 'Помилка',
            ],

            [
                'locale' => 'de',
                'group' => 'activity',
                'key' => 'activity.status.error',
                'value' => 'Fehler',
            ],
        ]);
    }
}
