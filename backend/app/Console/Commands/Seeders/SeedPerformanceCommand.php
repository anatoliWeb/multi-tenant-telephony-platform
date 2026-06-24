<?php

namespace App\Console\Commands\Seeders;

use App\Console\Commands\BaseCommand;
use App\Services\Seeding\SeederEnvironmentService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PerformanceSeeder;

class SeedPerformanceCommand extends BaseCommand
{
    protected $signature = 'app:seed-performance {--tenants=3 : Tenant count} {--users=150 : Users per tenant} {--allow-production : Permit production execution only with explicit override} {--force : Skip confirmation}';

    protected $description = 'Seed optional high-volume performance data.';

    public function handle(): int
    {
        $environment = app(SeederEnvironmentService::class);
        $this->renderSection('Performance Seeder');
        $this->line('Environment: '.$environment->environment());

        if ($environment->isProduction() && ! $this->option('allow-production')) {
            $this->renderError('Performance seeding is disabled in production unless --allow-production is provided.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirmOrAbort('Seed performance data now?', false)) {
            return self::SUCCESS;
        }

        app(CoreSeeder::class)->seed();

        $tenantCount = max(1, (int) $this->option('tenants'));
        $usersPerTenant = max(1, (int) $this->option('users'));
        $report = app(PerformanceSeeder::class)->seed(
            tenantCount: $tenantCount,
            usersPerTenant: $usersPerTenant,
            allowProduction: (bool) $this->option('allow-production'),
        );

        $this->renderSummary($report, 'Performance Seed Result');
        $this->renderSuccess('Performance seeding completed.');

        return self::SUCCESS;
    }
}
