<?php

namespace Database\Seeders\translations;

/**
 * Seeds validation translations.
 *
 * WHY:
 * Validation messaging is a critical part of UX and often requires
 * localization consistency between:
 * - backend validation
 * - frontend forms
 * - async validation
 * - API responses
 *
 * This architecture prepares the platform for:
 * - multilingual validation flows
 * - reusable validation UI
 * - dynamic admin-driven validation messages
 * - frontend/backend consistency
 *
 * IMPORTANT:
 * Laravel core validation translations should still exist
 * in static language files.
 *
 * This seeder focuses on:
 * - reusable UI validation messages
 * - SPA validation states
 * - runtime validation labels
 */
class ValidationTranslationsSeeder extends BaseTranslationsSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedTranslations([

            /*
            |--------------------------------------------------------------------------
            | Generic Validation Messages
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.required',
                'value' => 'This field is required.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.required',
                'value' => 'Це поле є обовʼязковим.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.required',
                'value' => 'Dieses Feld ist erforderlich.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.email',
                'value' => 'Please enter a valid email address.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.email',
                'value' => 'Введіть коректний email.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.email',
                'value' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.min',
                'value' => 'The value is too short.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.min',
                'value' => 'Значення занадто коротке.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.min',
                'value' => 'Der Wert ist zu kurz.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.max',
                'value' => 'The value is too long.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.max',
                'value' => 'Значення занадто довге.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.max',
                'value' => 'Der Wert ist zu lang.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.numeric',
                'value' => 'The value must be numeric.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.numeric',
                'value' => 'Значення повинно бути числом.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.numeric',
                'value' => 'Der Wert muss numerisch sein.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.boolean',
                'value' => 'The value must be true or false.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.boolean',
                'value' => 'Значення повинно бути true або false.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.boolean',
                'value' => 'Der Wert muss true oder false sein.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Password Validation
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.password.confirmed',
                'value' => 'Passwords do not match.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.password.confirmed',
                'value' => 'Паролі не співпадають.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.password.confirmed',
                'value' => 'Die Passwörter stimmen nicht überein.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.password.weak',
                'value' => 'Password is too weak.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.password.weak',
                'value' => 'Пароль занадто слабкий.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.password.weak',
                'value' => 'Das Passwort ist zu schwach.',
            ],

            /*
            |--------------------------------------------------------------------------
            | File Validation
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.file.required',
                'value' => 'Please select a file.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.file.required',
                'value' => 'Оберіть файл.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.file.required',
                'value' => 'Bitte wählen Sie eine Datei aus.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.file.invalid',
                'value' => 'Invalid file format.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.file.invalid',
                'value' => 'Невірний формат файлу.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.file.invalid',
                'value' => 'Ungültiges Dateiformat.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Async Form States
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.async.saving',
                'value' => 'Saving...',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.async.saving',
                'value' => 'Збереження...',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.async.saving',
                'value' => 'Speichern...',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.async.saved',
                'value' => 'Saved successfully.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.async.saved',
                'value' => 'Успішно збережено.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.async.saved',
                'value' => 'Erfolgreich gespeichert.',
            ],

            [
                'locale' => 'en',
                'group' => 'validation',
                'key' => 'validation.async.error',
                'value' => 'An unexpected error occurred.',
            ],

            [
                'locale' => 'uk',
                'group' => 'validation',
                'key' => 'validation.async.error',
                'value' => 'Сталася неочікувана помилка.',
            ],

            [
                'locale' => 'de',
                'group' => 'validation',
                'key' => 'validation.async.error',
                'value' => 'Ein unerwarteter Fehler ist aufgetreten.',
            ],
        ]);
    }
}
