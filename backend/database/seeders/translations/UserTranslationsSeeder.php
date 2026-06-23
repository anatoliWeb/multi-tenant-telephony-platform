<?php

namespace Database\Seeders\translations;

/**
 * Seeds dynamic user module translations.
 *
 * WHY:
 * User-related labels and runtime entities should support
 * database-driven localization.
 *
 * This architecture prepares the platform for:
 * - multilingual admin panels
 * - dynamic user management UI
 * - future tenant-specific user terminology
 * - runtime customization
 *
 * IMPORTANT:
 * Static UI actions/buttons should still remain
 * inside Laravel/Vue translation files.
 *
 * This seeder is focused on:
 * - entity labels
 * - table labels
 * - dynamic business terminology
 */
class UserTranslationsSeeder extends BaseTranslationsSeeder
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
                'group' => 'users',
                'key' => 'users.module.title',
                'value' => 'Users',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.module.title',
                'value' => 'Користувачі',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.module.title',
                'value' => 'Benutzer',
            ],

            /*
            |--------------------------------------------------------------------------
            | Entity Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.entity.user',
                'value' => 'User',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.entity.user',
                'value' => 'Користувач',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.entity.user',
                'value' => 'Benutzer',
            ],

            /*
            |--------------------------------------------------------------------------
            | Table Columns
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.columns.name',
                'value' => 'Name',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.columns.name',
                'value' => 'Імʼя',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.columns.name',
                'value' => 'Name',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.columns.email',
                'value' => 'Email',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.columns.email',
                'value' => 'Email',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.columns.email',
                'value' => 'E-Mail',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.columns.roles',
                'value' => 'Roles',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.columns.roles',
                'value' => 'Ролі',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.columns.roles',
                'value' => 'Rollen',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.columns.permissions',
                'value' => 'Permissions',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.columns.permissions',
                'value' => 'Дозволи',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.columns.permissions',
                'value' => 'Berechtigungen',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.columns.created_at',
                'value' => 'Created',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.columns.created_at',
                'value' => 'Створено',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.columns.created_at',
                'value' => 'Erstellt',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.status.active',
                'value' => 'Active',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.status.active',
                'value' => 'Активний',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.status.active',
                'value' => 'Aktiv',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.status.inactive',
                'value' => 'Inactive',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.status.inactive',
                'value' => 'Неактивний',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.status.inactive',
                'value' => 'Inaktiv',
            ],

            /*
            |--------------------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.actions.create',
                'value' => 'Create User',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.actions.create',
                'value' => 'Створити користувача',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.actions.create',
                'value' => 'Benutzer erstellen',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.actions.edit',
                'value' => 'Edit User',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.actions.edit',
                'value' => 'Редагувати користувача',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.actions.edit',
                'value' => 'Benutzer bearbeiten',
            ],

            [
                'locale' => 'en',
                'group' => 'users',
                'key' => 'users.actions.delete',
                'value' => 'Delete User',
            ],

            [
                'locale' => 'uk',
                'group' => 'users',
                'key' => 'users.actions.delete',
                'value' => 'Видалити користувача',
            ],

            [
                'locale' => 'de',
                'group' => 'users',
                'key' => 'users.actions.delete',
                'value' => 'Benutzer löschen',
            ],
        ]);
    }
}
