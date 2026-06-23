<?php

namespace Database\Seeders\translations;

/**
 * Seeds dynamic settings translations.
 *
 * WHY:
 * Settings are runtime-managed entities and therefore require
 * database-driven localization support.
 *
 * This architecture allows:
 * - admin-editable labels
 * - dynamic configuration descriptions
 * - multilingual settings UI
 * - future tenant-specific localization
 *
 * IMPORTANT:
 * Translation keys should remain stable.
 * Only translated labels/descriptions should change.
 */
class SettingsTranslationsSeeder extends BaseTranslationsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedTranslations([

            /*
            |--------------------------------------------------------------------------
            | General Settings
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.general.site_name',
                'value' => 'Site Name',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.general.site_name',
                'value' => 'Назва сайту',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.general.site_name',
                'value' => 'Seitenname',
            ],

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.general.default_locale',
                'value' => 'Default Locale',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.general.default_locale',
                'value' => 'Мова за замовчуванням',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.general.default_locale',
                'value' => 'Standardsprache',
            ],

            /*
            |--------------------------------------------------------------------------
            | Frontend Settings
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.frontend.theme',
                'value' => 'Frontend Theme',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.frontend.theme',
                'value' => 'Тема інтерфейсу',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.frontend.theme',
                'value' => 'Frontend-Thema',
            ],

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.frontend.enable_realtime',
                'value' => 'Enable Realtime',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.frontend.enable_realtime',
                'value' => 'Увімкнути realtime',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.frontend.enable_realtime',
                'value' => 'Realtime aktivieren',
            ],

            /*
            |--------------------------------------------------------------------------
            | Dashboard Settings
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.dashboard.default_layout',
                'value' => 'Default Dashboard Layout',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.dashboard.default_layout',
                'value' => 'Макет дашборду за замовчуванням',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.dashboard.default_layout',
                'value' => 'Standard-Dashboard-Layout',
            ],

            /*
            |--------------------------------------------------------------------------
            | Security Settings
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.security.password_min_length',
                'value' => 'Minimum Password Length',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.security.password_min_length',
                'value' => 'Мінімальна довжина пароля',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.security.password_min_length',
                'value' => 'Minimale Passwortlänge',
            ],

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.security.enable_2fa',
                'value' => 'Enable Two-Factor Authentication',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.security.enable_2fa',
                'value' => 'Увімкнути двофакторну автентифікацію',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.security.enable_2fa',
                'value' => 'Zwei-Faktor-Authentifizierung aktivieren',
            ],

            /*
            |--------------------------------------------------------------------------
            | Realtime Settings
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.realtime.provider',
                'value' => 'Realtime Provider',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.realtime.provider',
                'value' => 'Realtime провайдер',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.realtime.provider',
                'value' => 'Realtime-Anbieter',
            ],

            /*
            |--------------------------------------------------------------------------
            | Localization Settings
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'settings',
                'key' => 'settings.localization.available_locales',
                'value' => 'Available Languages',
            ],

            [
                'locale' => 'uk',
                'group' => 'settings',
                'key' => 'settings.localization.available_locales',
                'value' => 'Доступні мови',
            ],

            [
                'locale' => 'de',
                'group' => 'settings',
                'key' => 'settings.localization.available_locales',
                'value' => 'Verfügbare Sprachen',
            ],
        ]);
    }
}
