<?php

namespace Database\Seeders;

use App\Services\Seeding\SeederEnvironmentService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $environment = app(SeederEnvironmentService::class);

        $this->call(CoreSeeder::class);

        if ($environment->isProduction()) {
            return;
        }

        if ($environment->isTesting()) {
            if ($this->isEnabled('SEED_TEST_DATA', true)) {
                $this->call(TestSeeder::class);
            }

            return;
        }

        if ($this->isEnabled('SEED_DEMO_DATA', false)) {
            $this->call(DemoSeeder::class);
        }
    }

    protected function isEnabled(string $envKey, bool $default): bool
    {
        return filter_var(env($envKey, $default), FILTER_VALIDATE_BOOL);
    }
}
