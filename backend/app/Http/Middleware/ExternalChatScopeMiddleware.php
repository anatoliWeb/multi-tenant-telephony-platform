<?php

namespace App\Http\Middleware;

use App\Models\ChatWebhookEndpoint;
use App\Models\User;
use App\Services\Chat\ExternalChatTokenService;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalChatScopeMiddleware
{
    public function __construct(
        protected ExternalChatTokenService $tokenService,
        protected TenantContext $tenantContext,
        protected TenantBootstrapService $tenantBootstrapService
    ) {
    }

    /**
     * Authenticate external chat API request via Sanctum user or scoped endpoint token.
     *
     * Plain bearer token is hashed before lookup; raw token value is never persisted.
     * On successful token auth, request user resolver is swapped to endpoint creator.
     */
    public function handle(Request $request, Closure $next, string $requiredScope): Response
    {
        $this->tenantContext->clear();

        /** @var User|null $sanctumUser */
        $sanctumUser = auth('sanctum')->user();
        if ($sanctumUser instanceof User) {
            if (! $sanctumUser->hasAnyPermission(['chat.external_api.send', 'chat.external_api.manage', 'chat.admin.moderate'])) {
                throw new AuthorizationException('You are not allowed to send external chat messages.');
            }

            $this->resolveTenantContextFromRequest($request, $sanctumUser);

            return $next($request);
        }

        $plainToken = (string) ($request->bearerToken() ?? '');
        if ($plainToken === '') {
            abort(401, 'Unauthenticated');
        }

        $tokenHash = $this->tokenService->hashToken($plainToken);

        /** @var ChatWebhookEndpoint|null $endpoint */
        $endpoint = ChatWebhookEndpoint::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('metadata->token_hash', $tokenHash)
            ->first();

        if (! $endpoint) {
            abort(401, 'Unauthenticated');
        }

        $metadata = is_array($endpoint->metadata) ? $endpoint->metadata : [];
        if (! $this->tokenService->tokenHasScope($metadata, $requiredScope)) {
            throw new AuthorizationException('Forbidden');
        }

        $creator = $endpoint->creator;
        if (! $creator instanceof User) {
            abort(401, 'Unauthenticated');
        }

        if ($this->tenantContext->hasTenant() && $endpoint->tenant_id !== $this->tenantContext->tenantId()) {
            abort(403, 'Forbidden');
        }

        $request->setUserResolver(static fn (): User => $creator);
        $request->attributes->set('external_auth_mode', 'token');
        $request->attributes->set('external_token_endpoint_id', $endpoint->id);
        $request->attributes->set('external_token_scopes', data_get($metadata, 'token_scopes', []));
        $this->tenantContext->setTenant($endpoint->tenant);

        $metadata['token_last_used_at'] = now()->toISOString();
        $endpoint->metadata = $metadata;
        $endpoint->last_used_at = now();
        $endpoint->save();

        return $next($request);
    }

    private function resolveTenantContextFromRequest(Request $request, User $user): void
    {
        $identifier = trim((string) $request->header('X-Tenant-ID', ''));

        if ($identifier !== '') {
            $tenant = $this->tenantBootstrapService->resolveTenantByIdentifier($identifier);
            if ($tenant === null || ! $this->tenantBootstrapService->userHasActiveMembership($user, $tenant)) {
                throw new AuthorizationException('Tenant access denied.');
            }

            $this->tenantContext->setTenant($tenant);

            return;
        }

        $memberships = $user->activeTenantMemberships()
            ->with('tenant')
            ->orderBy('tenant_id')
            ->get();

        if ($memberships->count() === 1 && $memberships->first()?->tenant) {
            $this->tenantContext->setTenant($memberships->first()->tenant);

            return;
        }

        if (app()->runningUnitTests() && $memberships->isEmpty()) {
            $defaultTenant = $this->tenantBootstrapService->resolveTenantByIdentifier(TenantBootstrapService::DEFAULT_TENANT_UUID);
            if ($defaultTenant !== null) {
                $this->tenantContext->setTenant($defaultTenant);
            }

            return;
        }

        throw new AuthorizationException('Tenant access denied.');
    }
}
