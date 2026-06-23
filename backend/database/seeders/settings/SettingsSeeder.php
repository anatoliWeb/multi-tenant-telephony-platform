<?php

namespace Database\Seeders\settings;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            GeneralSettingsSeeder::class,
            FrontendSettingsSeeder::class,
            BackendSettingsSeeder::class,
            LocalizationSettingsSeeder::class,
            DashboardSettingsSeeder::class,
            RealtimeSettingsSeeder::class,
            SecuritySettingsSeeder::class,
            FeatureFlagsSeeder::class,
        ]);
    }
}
