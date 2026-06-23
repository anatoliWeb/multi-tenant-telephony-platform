<?php

namespace Database\Seeders\translations;

use Illuminate\Database\Seeder;
use App\Models\SystemTranslation;

/**
 * Shared dynamic translations seeder helper.
 *
 * WHY:
 * Centralizing translation upsert logic guarantees:
 * - idempotent seeding
 * - stable localization bootstrap
 * - consistent translation persistence
 * - scalable runtime translation architecture
 *
 * IMPORTANT:
 * Dynamic translations are intended ONLY for:
 * - database entities
 * - runtime/business translations
 * - admin-managed labels
 *
 * Static UI/system translations should still live
 * in Laravel/Vue translation files.
 */
abstract class BaseTranslationsSeeder extends Seeder
{
    /**
     * Seed dynamic translations.
     *
     * @param array<int, array<string, mixed>> $items
     */
    protected function seedTranslations(array $items): void
    {
        foreach ($items as $item) {

            /*
            |--------------------------------------------------------------------------
            | Unique Translation Scope
            |--------------------------------------------------------------------------
            */

            $scope = [
                'locale' => $item['locale'],
                'group' => $item['group'] ?? 'general',
                'key' => $item['key'],
            ];

            /*
            |--------------------------------------------------------------------------
            | Translation Payload
            |--------------------------------------------------------------------------
            */

            $payload = [
                'value' => $item['value'],
                'source' => $item['source'] ?? 'database',
                'description' => $item['description'] ?? null,

                'is_frontend' => $item['is_frontend'] ?? true,
                'is_backend' => $item['is_backend'] ?? true,

                'is_system' => $item['is_system'] ?? true,
                'is_active' => $item['is_active'] ?? true,

                'created_by' => null,
                'updated_by' => null,
            ];

            /*
            |--------------------------------------------------------------------------
            | Idempotent Upsert
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate translations during repeated seeding.
            */

            SystemTranslation::updateOrCreate(
                $scope,
                $payload
            );
        }
    }
}
