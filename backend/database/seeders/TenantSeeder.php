<?php

namespace Database\Seeders;

use App\Services\Tenancy\TenantBootstrapService;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantBootstrapService::class)->seedDemoMemberships();
    }
}
