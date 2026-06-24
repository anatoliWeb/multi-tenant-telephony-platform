<?php

namespace Database\Seeders;

use App\Services\Seeding\PerformanceSeedService;
use Illuminate\Database\Seeder;

class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed();
    }

    /**
     * Seed optional high-volume performance data.
     *
     * @return array<string, int|float>
     */
    public function seed(int $tenantCount = 3, int $usersPerTenant = 150, bool $allowProduction = false): array
    {
        return app(PerformanceSeedService::class)->seed($tenantCount, $usersPerTenant, $allowProduction);
    }
}
