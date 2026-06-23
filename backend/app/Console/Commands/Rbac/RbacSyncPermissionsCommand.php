<?php

namespace App\Console\Commands\Rbac;

use App\Console\Commands\BaseCommand;
use App\Services\Rbac\RbacMaintenanceService;

class RbacSyncPermissionsCommand extends BaseCommand
{
    protected $signature = 'rbac:sync-permissions {--force : Skip confirmation}';

    protected $description = 'Sync missing permissions from route middleware declarations.';

    public function __construct(
        protected RbacMaintenanceService $rbacMaintenance
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSection('RBAC Permission Sync');

        if (! $this->option('force') && ! $this->confirmOrAbort('Sync permissions from routes?', true)) {
            return self::SUCCESS;
        }

        $result = $this->rbacMaintenance->syncPermissionsFromRoutes();
        $this->renderSummary($result, 'Sync Result');

        return self::SUCCESS;
    }
}
