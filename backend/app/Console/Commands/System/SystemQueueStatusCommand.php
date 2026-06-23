<?php

namespace App\Console\Commands\System;

use App\Console\Commands\BaseCommand;
use App\Services\System\SystemHealthService;

class SystemQueueStatusCommand extends BaseCommand
{
    protected $signature = 'system:queue-status';

    protected $description = 'Display compact queue diagnostics baseline.';

    public function __construct(
        protected SystemHealthService $systemHealth
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $diagnostics = $this->systemHealth->queueDiagnostics();

        $this->renderSummary($diagnostics, 'Queue Diagnostics');
        $this->line('Tip: docker compose logs -f queue-worker');

        return self::SUCCESS;
    }
}

