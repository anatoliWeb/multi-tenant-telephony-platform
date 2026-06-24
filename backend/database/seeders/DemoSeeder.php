<?php

namespace Database\Seeders;

use App\Services\Seeding\SeederEnvironmentService;
use App\Services\Seeding\TenantDemoSeedService;
use Illuminate\Database\Seeder;

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
