<?php

namespace Database\Seeders\translations;

/**
 * Seeds notification translations.
 *
 * WHY:
 * Notifications are runtime-generated UI events and therefore require
 * database-driven localization support.
 *
 * This architecture prepares the platform for:
 * - realtime notification systems
 * - websocket events
 * - push notifications
 * - tenant-specific notification messaging
 * - activity-driven UI feedback
 *
 * IMPORTANT:
 * Static framework/system messages should still remain
 * inside Laravel/Vue translation files.
 *
 * This seeder focuses on:
 * - notification titles
 * - notification statuses
 * - toast messages
 * - realtime UI feedback
 */
class NotificationTranslationsSeeder extends BaseTranslationsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedTranslations([

            /*
            |--------------------------------------------------------------------------
            | Generic Notifications
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.success',
                'value' => 'Operation completed successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.success',
                'value' => 'Операцію успішно виконано.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.success',
                'value' => 'Vorgang erfolgreich abgeschlossen.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.error',
                'value' => 'Something went wrong.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.error',
                'value' => 'Щось пішло не так.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.error',
                'value' => 'Etwas ist schiefgelaufen.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.warning',
                'value' => 'Warning',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.warning',
                'value' => 'Попередження',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.warning',
                'value' => 'Warnung',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.info',
                'value' => 'Information',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.info',
                'value' => 'Інформація',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.info',
                'value' => 'Information',
            ],

            /*
            |--------------------------------------------------------------------------
            | CRUD Notifications
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.created',
                'value' => 'Created successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.created',
                'value' => 'Успішно створено.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.created',
                'value' => 'Erfolgreich erstellt.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.updated',
                'value' => 'Updated successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.updated',
                'value' => 'Успішно оновлено.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.updated',
                'value' => 'Erfolgreich aktualisiert.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.deleted',
                'value' => 'Deleted successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.deleted',
                'value' => 'Успішно видалено.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.deleted',
                'value' => 'Erfolgreich gelöscht.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Authentication Notifications
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.auth.logged_in',
                'value' => 'You have successfully logged in.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.auth.logged_in',
                'value' => 'Ви успішно увійшли.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.auth.logged_in',
                'value' => 'Sie haben sich erfolgreich angemeldet.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.auth.logged_out',
                'value' => 'You have successfully logged out.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.auth.logged_out',
                'value' => 'Ви успішно вийшли.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.auth.logged_out',
                'value' => 'Sie haben sich erfolgreich abgemeldet.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Token Notifications
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.tokens.created',
                'value' => 'Token created successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.tokens.created',
                'value' => 'Токен успішно створено.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.tokens.created',
                'value' => 'Token erfolgreich erstellt.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.tokens.revoked',
                'value' => 'Token revoked successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.tokens.revoked',
                'value' => 'Токен успішно відкликано.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.tokens.revoked',
                'value' => 'Token erfolgreich widerrufen.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Realtime Notifications
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.realtime.connected',
                'value' => 'Realtime connection established.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.realtime.connected',
                'value' => 'Realtime зʼєднання встановлено.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.realtime.connected',
                'value' => 'Realtime-Verbindung hergestellt.',
            ],

            [
                'locale' => 'en',
                'group' => 'notifications',
                'key' => 'notifications.realtime.disconnected',
                'value' => 'Realtime connection lost.',
            ],

            [
                'locale' => 'uk',
                'group' => 'notifications',
                'key' => 'notifications.realtime.disconnected',
                'value' => 'Realtime зʼєднання втрачено.',
            ],

            [
                'locale' => 'de',
                'group' => 'notifications',
                'key' => 'notifications.realtime.disconnected',
                'value' => 'Realtime-Verbindung verloren.',
            ],
        ]);
    }
}
