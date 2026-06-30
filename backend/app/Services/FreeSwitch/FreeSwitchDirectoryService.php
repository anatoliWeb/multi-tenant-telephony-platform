<?php

namespace App\Services\FreeSwitch;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantStatus;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;

class FreeSwitchDirectoryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DirectoryXmlBuilder $directoryXmlBuilder,
    ) {
    }

    /**
     * Resolve directory XML from the tenant-owned database.
     *
     * WHY:
     * The endpoint is only a scaffold until FreeSWITCH XML curl wiring is
     * intentionally enabled. The tenant must be chosen explicitly, because
     * runtime FreeSWITCH lookup domains are not the same thing as browser SIP
     * domains and we must not infer tenant identity from a raw PBX request.
     */
    public function resolve(string $user, string $domain): ?string
    {
        $configuredDomain = trim((string) config('freeswitch.directory_domain', ''));
        $requestedDomain = trim($domain);

        if ($configuredDomain === '' || $requestedDomain === '' || ! hash_equals($configuredDomain, $requestedDomain)) {
            return null;
        }

        $tenantId = trim((string) config('freeswitch.directory_tenant_id', ''));
        if ($tenantId === '') {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant || ! $this->tenantIsProvisionable($tenant)) {
            return null;
        }

        $previousTenant = $this->tenantContext->tenant();
        $this->tenantContext->setTenant($tenant);

        try {
            $extension = Extension::query()
                ->with(['tenant', 'credential'])
                ->forTenant($tenant)
                ->where('number', trim($user))
                ->where('status', ExtensionStatus::Active->value)
                ->first();

            if (! $extension) {
                return null;
            }

            // Only local demo mode may receive a password. Outside that gate
            // the endpoint still works as a safe scaffold, but it omits the
            // secret and keeps the static XML fallback as the local bootstrap.
            $includePassword = $this->shouldIncludeLocalDemoPassword();

            return $this->directoryXmlBuilder->build($extension, $includePassword);
        } finally {
            $this->tenantContext->setTenant($previousTenant);
        }
    }

    private function shouldIncludeLocalDemoPassword(): bool
    {
        return config('app.env') === 'local'
            && config('freeswitch.enabled', false)
            && config('freeswitch.local_demo_credentials', false);
    }

    private function tenantIsProvisionable(Tenant $tenant): bool
    {
        $status = $tenant->status;

        if ($status instanceof TenantStatus) {
            return $status === TenantStatus::Active;
        }

        return (string) $status === TenantStatus::Active->value;
    }
}
