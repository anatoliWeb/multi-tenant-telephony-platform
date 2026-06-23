<?php

namespace Database\Seeders\translations;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Master translations seeder.
 *
 * WHY:
 * Centralizes all dynamic localization bootstrap logic
 * into a single seeding entry point.
 *
 * This seeder is responsible for:
 * - RBAC translations
 * - settings translations
 * - dashboard translations
 * - auth translations
 * - validation translations
 * - notification translations
 *
 * IMPORTANT:
 * Seeder execution is idempotent because all translation
 * seeders use updateOrCreate().
 */
class TranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([

            /*
            |--------------------------------------------------------------------------
            | RBAC
            |--------------------------------------------------------------------------
            */

            RoleTranslationsSeeder::class,
            PermissionTranslationsSeeder::class,

            /*
            |--------------------------------------------------------------------------
            | Core Modules
            |--------------------------------------------------------------------------
            */
            UserTranslationsSeeder::class,
            TokenTranslationsSeeder::class,
            ActivityTranslationsSeeder::class,
            DashboardTranslationsSeeder::class,

            /*
           |--------------------------------------------------------------------------
           | Platform / System
           |--------------------------------------------------------------------------
           */

            SettingsTranslationsSeeder::class,
            AuthTranslationsSeeder::class,
            ValidationTranslationsSeeder::class,
            NotificationTranslationsSeeder::class,
        ]);
    }
}
