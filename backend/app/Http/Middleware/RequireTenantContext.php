<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->requireTenant();

        return $next($request);
    }
}
