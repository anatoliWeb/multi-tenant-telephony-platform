<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Support\TenantSeedService;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantSeedService::class)->seedLegacyDemoMemberships();
    }
}
