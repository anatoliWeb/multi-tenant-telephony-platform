<?php

namespace App\Services\Tenancy;

use App\Exceptions\Tenancy\TenantContextRequiredException;
use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function requireTenant(): Tenant
    {
        return $this->tenant ?? throw new TenantContextRequiredException();
    }

    public function tenantId(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
