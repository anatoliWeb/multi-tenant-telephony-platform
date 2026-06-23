<?php

namespace App\Console\Commands\System;

use App\Console\Commands\BaseCommand;
use App\Services\System\SystemHealthService;

class SystemDebugInfoCommand extends BaseCommand
{
    protected $signature = 'system:debug-info';

    protected $description = 'Display compact debug/runtime environment information.';

    public function __construct(
        protected SystemHealthService $systemHealth
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSummary(
            $this->systemHealth->debugInfo(),
            'System Debug Info'
        );

        return self::SUCCESS;
    }
}
