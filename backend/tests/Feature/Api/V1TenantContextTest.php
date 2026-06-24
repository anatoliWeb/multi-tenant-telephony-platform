<?php

namespace Tests\Feature\Api;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_tenants_and_resolve_current_selection(): void
    {
        [$user, $tenantOne, $tenantTwo] = $this->createMultiTenantUser();

        Sanctum::actingAs($user);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenantOne->id)
            ->getJson('/api/v1/user/tenants');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_tenant_id', $tenantOne->id)
            ->assertJsonCount(2, 'data.tenants')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tenants' => [
                        [
                            'id',
                            'tenant_id',
                            'user_id',
                            'status',
                            'tenant' => ['id', 'name', 'slug'],
                        ],
                    ],
                    'current_tenant_id',
                ],
            ]);

        $switchResponse = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/tenant/switch', [
                'tenant_uuid' => $tenantTwo->id,
            ]);

        $switchResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_tenant_id', $tenantTwo->id)
            ->assertJsonPath('data.tenant.id', $tenantTwo->id);

        $currentResponse = $this
            ->actingAs($user, 'sanctum')
            ->withHeader('X-Tenant-ID', $tenantTwo->id)
            ->getJson('/api/v1/user/tenant');

        $currentResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_tenant_id', $tenantTwo->id)
            ->assertJsonPath('data.tenant.id', $tenantTwo->id);
    }

    public function test_tenant_endpoint_requires_active_tenant_context(): void
    {
        [$user] = $this->createMultiTenantUser();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/tenant')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tenant context is required');
    }

    public function test_platform_only_admin_cannot_use_tenant_context_without_membership(): void
    {
        $tenant = $this->createTenant('tenant-platform-isolation');
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrator']);

        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        Sanctum::actingAs($admin);

        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/user/tenants')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{0: User, 1: Tenant, 2: Tenant}
     */
    private function createMultiTenantUser(): array
    {
        $user = User::factory()->create();
        $tenantOne = $this->createTenant('tenant-one');
        $tenantTwo = $this->createTenant('tenant-two');

        $this->createMembership($tenantOne, $user, TenantMembershipStatus::Active);
        $this->createMembership($tenantTwo, $user, TenantMembershipStatus::Active);

        return [$user, $tenantOne, $tenantTwo];
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => now(),
            'suspended_at' => null,
        ]);
    }

    private function createMembership(Tenant $tenant, User $user, TenantMembershipStatus $status): TenantMembership
    {
        return TenantMembership::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => $status,
            'invited_by' => null,
            'invited_at' => null,
            'accepted_at' => now(),
            'activated_at' => $status === TenantMembershipStatus::Active ? now() : null,
            'suspended_at' => $status === TenantMembershipStatus::Suspended ? now() : null,
        ]);
    }
}
