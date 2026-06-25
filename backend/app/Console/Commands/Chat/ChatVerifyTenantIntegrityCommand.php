<?php

namespace App\Console\Commands\Chat;

use App\Console\Commands\BaseCommand;
use App\Services\Chat\ChatTenantIntegrityService;

class ChatVerifyTenantIntegrityCommand extends BaseCommand
{
    protected $signature = 'chat:verify-tenant-integrity {--json : Output the audit as JSON}';

    protected $description = 'Inspect tenant ownership, nullability, and consistency across chat tables.';

    public function __construct(
        private readonly ChatTenantIntegrityService $integrityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $audit = $this->integrityService->inspect();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return (bool) $audit['has_failures'] ? self::FAILURE : self::SUCCESS;
        }

        foreach ($audit['tables'] as $table => $details) {
            $this->renderSummary([
                'table' => $table,
                'tenant_id_exists' => $details['tenant_id_exists'] ? 'yes' : 'no',
                'tenant_id_nullable' => (string) $details['tenant_id_nullable'],
                'total_rows' => (string) $details['total_rows'],
                'tenant_id_null_rows' => $details['tenant_id_null_rows'] === null
                    ? 'n/a'
                    : (string) $details['tenant_id_null_rows'],
            ], "Chat Tenant Audit: {$table}");
        }

        $this->renderSummary($audit['mismatches'], 'Chat Tenant Mismatch Counts');

        return (bool) $audit['has_failures'] ? self::FAILURE : self::SUCCESS;
    }
}
