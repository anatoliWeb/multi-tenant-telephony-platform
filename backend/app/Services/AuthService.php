<?php

namespace App\Services;

use App\Events\Auth\TokenRevoked;
use App\DTO\AuthContextDTO;
use App\Models\User;
use App\Observers\PersonalAccessTokenObserver;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected PermissionCacheService $permissionCacheService,
        protected TenantContext $tenantContext,
    ) {
    }

    /**
     * Issue API token for token-based clients.
     *
     * WHY:
     * Token issuing is auth business logic and should not live in controller.
     *
     * @param array{email: string, password: string} $credentials
     *
     * @return array<string, mixed>
     */
    public function issueToken(array $credentials): array
    {
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
            ...$this->buildAuthPayload($user),
        ];
    }

    /**
     * Login user through web session guard.
     *
     * WHY:
     * Vue admin uses first-party session authentication.
     *
     * @param array{email: string, password: string, remember?: bool|null} $credentials
     *
     * @return array<string, mixed>
     */
    public function sessionLogin(Request $request, array $credentials): array
    {
        $remember = (bool) ($credentials['remember'] ?? false);

        $attemptCredentials = [
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ];

        if (!Auth::guard('web')->attempt($attemptCredentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        return $this->buildAuthPayload($request->user());
    }

    /**
     * Build current session/API user context.
     *
     * WHY:
     * Angular token clients and Vue session clients must receive
     * the same auth payload shape.
     *
     * @return array<string, mixed>
     */
    public function context(?Authenticatable $user): array
    {
        return $this->buildAuthPayload($user instanceof User ? $user : null);
    }

    /**
     * Destroy web session.
     */
    public function sessionLogout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Revoke current bearer token.
     */
    public function logoutToken(?User $user): void
    {
        $token = $user?->currentAccessToken();
        if (!$token) {
            return;
        }

        event(new TokenRevoked(
            tokenId: $token->id,
            tokenName: $token->name,
            tokenableId: (int) $token->tokenable_id,
            actorId: $user?->id,
            revokeReason: 'logout',
            occurredAt: now()->toIso8601String(),
        ));

        PersonalAccessTokenObserver::suppressNextDeleted();
        $token->delete();
    }

    /**
     * Build stable auth response payload.
     *
     * WHY:
     * Keeps user, roles and permissions response contract centralized.
     *
     * @return array<string, mixed>
     */
    protected function buildAuthPayload(?User $user): array
    {
        return (new AuthContextDTO(
            user: $this->toSessionUser($user),
            permissions: $this->resolveEffectivePermissions($user),
            platformPermissions: $user ? $this->permissionCacheService->getPlatformPermissionsForUser($user) : [],
            tenantPermissions: $user && $this->tenantContext->hasTenant()
                ? $this->permissionCacheService->getTenantPermissionsForUser($user, $this->tenantContext->requireTenant())
                : [],
            roles: $user ? $user->roles()->where('scope', 'platform')->pluck('name')->values()->all() : [],
        ))->toArray();
    }

    /**
     * Build effective permission set for API auth payloads.
     *
     * WHY:
     * Resolved permissions must include role permissions + direct permissions
     * and exclude explicitly denied permissions.
     *
     * @return array<int, string>
     */
    protected function resolveEffectivePermissions(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return $this->permissionCacheService->getEffectivePermissionsForUser($user);
    }

    /**
     * Convert authenticated user to safe session/API shape.
     *
     * @return array<string, mixed>|null
     */
    protected function toSessionUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
