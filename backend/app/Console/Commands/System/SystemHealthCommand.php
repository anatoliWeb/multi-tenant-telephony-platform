<?php

namespace App\Console\Commands\System;

use App\Console\Commands\BaseCommand;
use App\Services\System\SystemHealthService;

class SystemHealthCommand extends BaseCommand
{
    protected $signature = 'system:health';

    protected $description = 'Run lightweight platform health checks.';

    public function __construct(
        protected SystemHealthService $systemHealth
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $health = $this->systemHealth->health();

        $this->renderSummary($health, 'System Health');

        if (in_array('failed', $health, true)) {
            $this->renderError('One or more health checks failed.');
            return self::FAILURE;
        }

        $this->renderSuccess('All health checks passed.');
        return self::SUCCESS;
    }
}
