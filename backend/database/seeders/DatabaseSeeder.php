<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\settings\SettingsSeeder;
use Database\Seeders\Translations\TranslationsSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            TenantSeeder::class,
            ActivitySeeder::class,
            SettingsSeeder::class,
            TranslationsSeeder::class,
        ]);

        if (! app()->environment('production') && (bool) env('CHAT_DEMO_SEED', false)) {
            $this->call(ChatDemoSeeder::class);
        }
    }
}
