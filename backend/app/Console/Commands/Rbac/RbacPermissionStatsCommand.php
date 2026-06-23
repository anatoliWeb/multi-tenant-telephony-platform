<?php

namespace App\Console\Commands\Rbac;

use App\Console\Commands\BaseCommand;
use App\Services\Rbac\RbacMaintenanceService;

class RbacPermissionStatsCommand extends BaseCommand
{
    protected $signature = 'rbac:permission-stats';

    protected $description = 'Show RBAC permission coverage and assignment statistics.';

    public function __construct(
        protected RbacMaintenanceService $rbacMaintenance
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSummary(
            $this->rbacMaintenance->permissionStats(),
            'RBAC Permission Stats'
        );

        return self::SUCCESS;
    }
}
