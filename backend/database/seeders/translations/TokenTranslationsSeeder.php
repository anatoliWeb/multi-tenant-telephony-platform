<?php

namespace Database\Seeders\translations;

/**
 * Seeds dynamic API token translations.
 *
 * WHY:
 * Tokens are runtime-managed security entities and therefore require
 * database-driven localization support.
 *
 * This architecture prepares the platform for:
 * - multilingual token management UI
 * - API access management
 * - service integrations
 * - automation workflows
 * - future OAuth/service-account expansion
 *
 * IMPORTANT:
 * Static UI/system texts should remain inside Laravel/Vue translation files.
 *
 * This seeder focuses on:
 * - token entity labels
 * - token management terminology
 * - runtime token statuses/actions
 */
class TokenTranslationsSeeder extends BaseTranslationsSeeder
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
                'group' => 'tokens',
                'key' => 'tokens.module.title',
                'value' => 'API Tokens',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.module.title',
                'value' => 'API токени',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.module.title',
                'value' => 'API-Token',
            ],

            /*
            |--------------------------------------------------------------------------
            | Entity Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.entity.token',
                'value' => 'Token',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.entity.token',
                'value' => 'Токен',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.entity.token',
                'value' => 'Token',
            ],

            /*
            |--------------------------------------------------------------------------
            | Table Columns
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.columns.name',
                'value' => 'Name',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.columns.name',
                'value' => 'Назва',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.columns.name',
                'value' => 'Name',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.columns.abilities',
                'value' => 'Abilities',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.columns.abilities',
                'value' => 'Можливості',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.columns.abilities',
                'value' => 'Berechtigungen',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.columns.last_used_at',
                'value' => 'Last Used',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.columns.last_used_at',
                'value' => 'Останнє використання',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.columns.last_used_at',
                'value' => 'Zuletzt verwendet',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.columns.expires_at',
                'value' => 'Expires',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.columns.expires_at',
                'value' => 'Термін дії',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.columns.expires_at',
                'value' => 'Läuft ab',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.columns.created_at',
                'value' => 'Created',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.columns.created_at',
                'value' => 'Створено',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.columns.created_at',
                'value' => 'Erstellt',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status Labels
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.status.active',
                'value' => 'Active',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.status.active',
                'value' => 'Активний',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.status.active',
                'value' => 'Aktiv',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.status.expired',
                'value' => 'Expired',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.status.expired',
                'value' => 'Протермінований',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.status.expired',
                'value' => 'Abgelaufen',
            ],

            /*
            |--------------------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.actions.create',
                'value' => 'Create Token',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.actions.create',
                'value' => 'Створити токен',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.actions.create',
                'value' => 'Token erstellen',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.actions.revoke',
                'value' => 'Revoke Token',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.actions.revoke',
                'value' => 'Відкликати токен',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.actions.revoke',
                'value' => 'Token widerrufen',
            ],

            [
                'locale' => 'en',
                'group' => 'tokens',
                'key' => 'tokens.actions.delete',
                'value' => 'Delete Token',
            ],

            [
                'locale' => 'uk',
                'group' => 'tokens',
                'key' => 'tokens.actions.delete',
                'value' => 'Видалити токен',
            ],

            [
                'locale' => 'de',
                'group' => 'tokens',
                'key' => 'tokens.actions.delete',
                'value' => 'Token löschen',
            ],
        ]);
    }
}
