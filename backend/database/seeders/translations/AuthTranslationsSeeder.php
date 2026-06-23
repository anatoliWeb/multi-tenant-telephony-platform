<?php

namespace Database\Seeders\translations;

/**
 * Seeds authentication and authorization translations.
 *
 * WHY:
 * Authentication flows contain many runtime/business messages that may
 * require dynamic localization support across:
 * - SPA authentication
 * - API authentication
 * - security workflows
 * - session handling
 * - account recovery
 *
 * This architecture prepares the platform for:
 * - multilingual auth flows
 * - tenant-specific auth messaging
 * - enterprise security UI
 * - runtime-managed authentication screens
 *
 * IMPORTANT:
 * Validation/system framework messages should still primarily remain
 * inside Laravel/Vue static language files.
 *
 * This seeder focuses on:
 * - auth entity labels
 * - runtime auth actions
 * - security statuses/messages
 */
class AuthTranslationsSeeder extends BaseTranslationsSeeder
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
                'group' => 'auth',
                'key' => 'auth.module.title',
                'value' => 'Authentication',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.module.title',
                'value' => 'Автентифікація',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.module.title',
                'value' => 'Authentifizierung',
            ],

            /*
            |--------------------------------------------------------------------------
            | Form Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.fields.email',
                'value' => 'Email',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.fields.email',
                'value' => 'Email',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.fields.email',
                'value' => 'E-Mail',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.fields.password',
                'value' => 'Password',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.fields.password',
                'value' => 'Пароль',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.fields.password',
                'value' => 'Passwort',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.fields.password_confirmation',
                'value' => 'Confirm Password',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.fields.password_confirmation',
                'value' => 'Підтвердження пароля',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.fields.password_confirmation',
                'value' => 'Passwort bestätigen',
            ],

            /*
            |--------------------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.actions.login',
                'value' => 'Login',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.actions.login',
                'value' => 'Увійти',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.actions.login',
                'value' => 'Anmelden',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.actions.logout',
                'value' => 'Logout',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.actions.logout',
                'value' => 'Вийти',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.actions.logout',
                'value' => 'Abmelden',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.actions.register',
                'value' => 'Register',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.actions.register',
                'value' => 'Реєстрація',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.actions.register',
                'value' => 'Registrieren',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.actions.reset_password',
                'value' => 'Reset Password',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.actions.reset_password',
                'value' => 'Скинути пароль',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.actions.reset_password',
                'value' => 'Passwort zurücksetzen',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status Messages
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.status.logged_in',
                'value' => 'Successfully logged in',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.status.logged_in',
                'value' => 'Вхід успішний',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.status.logged_in',
                'value' => 'Erfolgreich angemeldet',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.status.logged_out',
                'value' => 'Successfully logged out',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.status.logged_out',
                'value' => 'Вихід успішний',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.status.logged_out',
                'value' => 'Erfolgreich abgemeldet',
            ],

            /*
            |--------------------------------------------------------------------------
            | Error Messages
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.errors.invalid_credentials',
                'value' => 'Invalid credentials',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.errors.invalid_credentials',
                'value' => 'Невірні облікові дані',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.errors.invalid_credentials',
                'value' => 'Ungültige Anmeldedaten',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.errors.unauthorized',
                'value' => 'Unauthorized access',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.errors.unauthorized',
                'value' => 'Доступ заборонено',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.errors.unauthorized',
                'value' => 'Unbefugter Zugriff',
            ],

            [
                'locale' => 'en',
                'group' => 'auth',
                'key' => 'auth.errors.session_expired',
                'value' => 'Session expired',
            ],

            [
                'locale' => 'uk',
                'group' => 'auth',
                'key' => 'auth.errors.session_expired',
                'value' => 'Сесію завершено',
            ],

            [
                'locale' => 'de',
                'group' => 'auth',
                'key' => 'auth.errors.session_expired',
                'value' => 'Sitzung abgelaufen',
            ],
        ]);
    }
}
