<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Support\SeederEnvironmentService;
use Database\Seeders\Support\TenantDemoSeedService;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed();
    }

    /**
     * @return array<string, int>
     */
    public function seed(): array
    {
        app(SeederEnvironmentService::class)->assertNotProduction('demo seeding');

        return app(TenantDemoSeedService::class)->seed();
    }
}
