<?php

namespace Database\Seeders\translations;

/**
 * Seeds role localization keys.
 *
 * TECHNICAL IDENTIFIER POLICY:
 * `roles.name` stays immutable (admin/manager/user).
 * This seeder adds only presentation-layer translations.
 */
class RoleTranslationsSeeder extends BaseTranslationsSeeder
{
    public function run(): void
    {
        $labels = [
            'admin' => ['en' => 'Administrator', 'uk' => 'Адміністратор', 'de' => 'Administrator'],
            'manager' => ['en' => 'Manager', 'uk' => 'Менеджер', 'de' => 'Manager'],
            'user' => ['en' => 'User', 'uk' => 'Користувач', 'de' => 'Benutzer'],
        ];

        $descriptions = [
            'admin' => [
                'en' => 'Full platform access, including RBAC and system configuration.',
                'uk' => 'Повний доступ до платформи, включно з RBAC і системною конфігурацією.',
                'de' => 'Vollzugriff auf die Plattform einschließlich RBAC und Systemkonfiguration.',
            ],
            'manager' => [
                'en' => 'Operational access for team and content management workflows.',
                'uk' => 'Операційний доступ для керування командою та робочими процесами.',
                'de' => 'Operativer Zugriff für Team- und Content-Management-Workflows.',
            ],
            'user' => [
                'en' => 'Standard account with limited business operations.',
                'uk' => 'Базовий акаунт з обмеженими бізнес-операціями.',
                'de' => 'Standardkonto mit eingeschränkten Geschäftsoperationen.',
            ],
        ];

        $rows = [];

        foreach ($labels as $key => $translations) {
            foreach ($translations as $locale => $value) {
                $rows[] = [
                    'locale' => $locale,
                    'group' => 'roles',
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        foreach ($descriptions as $key => $translations) {
            foreach ($translations as $locale => $value) {
                $rows[] = [
                    'locale' => $locale,
                    'group' => 'role_descriptions',
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        $this->seedTranslations($rows);
    }
}
