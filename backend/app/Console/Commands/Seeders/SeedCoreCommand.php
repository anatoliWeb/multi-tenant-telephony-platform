<?php

namespace App\Console\Commands\Seeders;

use App\Console\Commands\BaseCommand;
use App\Services\Seeding\SeederEnvironmentService;
use Database\Seeders\CoreSeeder;

class SeedCoreCommand extends BaseCommand
{
    protected $signature = 'app:seed-core {--force : Skip confirmation}';

    protected $description = 'Seed mandatory system data only.';

    public function handle(): int
    {
        $environment = app(SeederEnvironmentService::class)->environment();

        $this->renderSection('Core Seeder');
        $this->line("Environment: {$environment}");

        if (! $this->option('force') && ! $this->confirmOrAbort('Seed core system data now?', false)) {
            return self::SUCCESS;
        }

        $report = app(CoreSeeder::class)->seed();
        $this->renderSummary($report, 'Core Seed Result');
        $this->renderSuccess('Core seeding completed.');

        return self::SUCCESS;
    }
}
