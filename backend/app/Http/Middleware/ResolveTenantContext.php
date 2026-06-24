<?php

namespace App\Http\Middleware;

use App\Exceptions\Tenancy\TenantAccessDeniedException;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantBootstrapService $tenantBootstrapService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();

        $identifier = trim((string) $request->header('X-Tenant-ID', ''));

        if ($identifier === '') {
            return $next($request);
        }

        $tenant = $this->tenantBootstrapService->resolveTenantByIdentifier($identifier);

        if ($tenant === null) {
            throw new TenantAccessDeniedException('Tenant access denied');
        }

        $user = $request->user();
        if ($user === null) {
            throw new TenantAccessDeniedException('Tenant access denied');
        }

        if (! $this->tenantBootstrapService->userHasActiveMembership($user, $tenant)) {
            throw new TenantAccessDeniedException('Tenant access denied');
        }

        $this->tenantContext->setTenant($tenant);

        return $next($request);
    }
}
