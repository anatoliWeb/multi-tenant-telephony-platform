<?php

namespace Database\Seeders\settings;

/**
 * Seeds localization and language defaults.
 *
 * WHY:
 * Localization settings define which languages are available in the UI,
 * which locale is used by default, and how the platform should behave
 * when user-specific or role-specific language settings are not available.
 *
 * These values are intentionally configurable because enterprise SaaS systems
 * often need different language availability per tenant, role, or user.
 */
class LocalizationSettingsSeeder extends BaseSettingsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSettings([
            [
                'key' => 'localization.available_locales',
                'label' => 'Available Locales',
                'group' => 'localization',
                'description' => 'List of locales available in the platform language switcher.',
                'type' => 'json',
                'value' => ['en', 'uk', 'de'],
                'default_value' => ['en', 'uk', 'de'],
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'localization.default_locale',
                'label' => 'Default Locale',
                'group' => 'localization',
                'description' => 'Default locale used when no user-specific locale override exists.',
                'type' => 'string',
                'value' => 'en',
                'default_value' => 'en',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'localization.fallback_locale',
                'label' => 'Fallback Locale',
                'group' => 'localization',
                'description' => 'Fallback locale used when a translation key is missing.',
                'type' => 'string',
                'value' => 'en',
                'default_value' => 'en',
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'localization.allow_user_locale_switching',
                'label' => 'Allow User Locale Switching',
                'group' => 'localization',
                'description' => 'Controls whether users can manually switch interface language.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'localization.hide_switcher_if_single_locale',
                'label' => 'Hide Switcher If Single Locale',
                'group' => 'localization',
                'description' => 'Hides the language switcher when only one locale is available.',
                'type' => 'boolean',
                'value' => true,
                'default_value' => true,
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'localization.locale_labels',
                'label' => 'Locale Labels',
                'group' => 'localization',
                'description' => 'Human-readable locale names displayed in language switchers.',
                'type' => 'json',
                'value' => [
                    'en' => 'English',
                    'uk' => 'Українська',
                    'de' => 'Deutsch',
                ],
                'default_value' => [
                    'en' => 'English',
                    'uk' => 'Українська',
                    'de' => 'Deutsch',
                ],
                'is_frontend' => true,
                'is_backend' => true,
            ],
            [
                'key' => 'localization.locale_short_labels',
                'label' => 'Locale Short Labels',
                'group' => 'localization',
                'description' => 'Short locale labels shown in compact UI controls.',
                'type' => 'json',
                'value' => [
                    'en' => 'EN',
                    'uk' => 'UK',
                    'de' => 'DE',
                ],
                'default_value' => [
                    'en' => 'EN',
                    'uk' => 'UK',
                    'de' => 'DE',
                ],
                'is_frontend' => true,
                'is_backend' => false,
            ],
            [
                'key' => 'localization.date_locale',
                'label' => 'Date Locale',
                'group' => 'localization',
                'description' => 'Locale used for formatting dates and time in UI components.',
                'type' => 'string',
                'value' => 'en',
                'default_value' => 'en',
                'is_frontend' => true,
                'is_backend' => true,
            ],
        ]);
    }
}
