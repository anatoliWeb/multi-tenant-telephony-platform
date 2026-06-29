<?php

namespace Database\Seeders;

use App\Services\Seeding\TenantSeedService;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantSeedService::class)->seedLegacyDemoMemberships();
    }
}
