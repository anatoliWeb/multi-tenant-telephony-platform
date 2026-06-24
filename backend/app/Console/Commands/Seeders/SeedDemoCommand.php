<?php

namespace App\Console\Commands\Seeders;

use App\Console\Commands\BaseCommand;
use App\Services\Seeding\SeederEnvironmentService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DemoSeeder;

class SeedDemoCommand extends BaseCommand
{
    protected $signature = 'app:seed-demo {--force : Skip confirmation}';

    protected $description = 'Seed deterministic demo tenants, users, memberships, and tenant roles.';

    public function handle(): int
    {
        $environment = app(SeederEnvironmentService::class);
        $this->renderSection('Demo Seeder');
        $this->line('Environment: '.$environment->environment());

        if ($environment->isProduction()) {
            $this->renderError('Demo seeding is disabled in production.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirmOrAbort('Seed demo data now?', false)) {
            return self::SUCCESS;
        }

        $coreReport = app(CoreSeeder::class)->seed();
        $demoReport = app(DemoSeeder::class)->seed();

        $this->renderSummary([
            'core_permissions' => $coreReport['permissions'],
            'core_roles' => $coreReport['roles'],
            'core_settings' => $coreReport['settings'],
            'core_translations' => $coreReport['translations'],
            ...$demoReport,
        ], 'Demo Seed Result');
        $this->renderSuccess('Demo seeding completed.');

        return self::SUCCESS;
    }
}
