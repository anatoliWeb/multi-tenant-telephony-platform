<?php

namespace Tests\Feature\Chat\Concerns;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;

trait InteractsWithTenantScopedChat
{
    use BuildsTenantIsolationFixtures;

    protected ?Tenant $chatTenant = null;

    protected function setUpInteractsWithTenantScopedChat(): void
    {
        $this->activateChatTenant();
    }

    protected function actingAsTenantChatUser(array $permissions): User
    {
        $user = $this->makeTenantChatUserWithPermissions($permissions);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeTenantChatUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        return $this->prepareTenantChatUser($user, $permissions);
    }

    protected function prepareTenantChatUser(User $user, array $permissions): User
    {
        $tenant = $this->activateChatTenant();

        TenantMembership::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => TenantMembershipStatus::Active->value,
                'invited_by' => null,
                'invited_at' => null,
                'accepted_at' => now(),
                'activated_at' => now(),
                'suspended_at' => null,
            ]
        );

        if ($permissions !== []) {
            $this->assignTenantPermissions($user, $tenant, $permissions, 'chat-test-role-'.Str::lower(Str::random(8)));
        }

        $externalApiPermissions = array_values(array_filter(
            $permissions,
            static fn (string $permission): bool => str_starts_with($permission, 'chat.external_api.')
        ));

        if ($externalApiPermissions !== []) {
            $this->assignPlatformPermissions($user, $externalApiPermissions);
        }

        app(PermissionCacheService::class)->forgetForUser($user);

        return $user;
    }

    protected function activateChatTenant(?Tenant $tenant = null): Tenant
    {
        $tenant ??= $this->chatTenant();

        app(TenantContext::class)->setTenant($tenant);
        $this->withHeader('X-Tenant-ID', (string) $tenant->id);

        return $this->chatTenant = $tenant;
    }

    protected function chatTenant(): Tenant
    {
        if ($this->chatTenant instanceof Tenant) {
            return $this->chatTenant;
        }

        return $this->chatTenant = Tenant::query()->firstOrCreate(
            ['id' => TenantBootstrapService::DEFAULT_TENANT_UUID],
            [
                'name' => 'Default Test Tenant',
                'slug' => 'default-test-tenant',
                'status' => TenantStatus::Active->value,
                'timezone' => 'UTC',
                'locale' => 'en',
                'currency' => 'USD',
                'settings' => [],
                'activated_at' => now(),
                'suspended_at' => null,
            ]
        );
    }
}
