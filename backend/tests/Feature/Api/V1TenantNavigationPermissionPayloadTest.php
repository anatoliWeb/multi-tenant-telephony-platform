<?php

namespace Tests\Feature\Api;

use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1TenantNavigationPermissionPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_owner_receives_feature_permissions_in_tenant_context_payloads(): void
    {
        $this->seed(TestSeeder::class);

        $user = \App\Models\User::query()->where('email', 'test-tenant-owner@test.local')->firstOrFail();
        $tenant = \App\Models\Tenant::query()->where('slug', 'default-tenant')->firstOrFail();

        Sanctum::actingAs($user);

        $tenantsResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/user/tenants');

        $tenantsResponse->assertOk();
        $this->assertSameCanonicalFeaturePermissions(
            $tenantsResponse->json('data.tenant_permissions', [])
        );

        $currentResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/user/tenant');

        $currentResponse->assertOk();
        $this->assertSameCanonicalFeaturePermissions(
            $currentResponse->json('data.tenant_permissions', [])
        );
        $this->assertSame(
            $currentResponse->json('data.tenant_permissions', []),
            $currentResponse->json('data.permissions', [])
        );
    }

    public function test_limited_user_receives_only_limited_navigation_permissions(): void
    {
        $this->seed(TestSeeder::class);

        $user = \App\Models\User::query()->where('email', 'test-tenant-a-only@test.local')->firstOrFail();
        $tenant = \App\Models\Tenant::query()->where('slug', 'default-tenant')->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/user/tenant');

        $response->assertOk();
        $permissions = $response->json('data.tenant_permissions', []);

        foreach (['chat.view', 'chat.conversations.view', 'contacts.view', 'extensions.view', 'phone_numbers.view', 'call_logs.view', 'call_logs.view_own'] as $permission) {
            $this->assertContains($permission, $permissions);
        }

        foreach (['contacts.create', 'extensions.manage_credentials', 'phone_numbers.release', 'call_logs.view_all'] as $forbiddenPermission) {
            $this->assertNotContains($forbiddenPermission, $permissions);
        }
    }

    /**
     * @param array<int, string> $permissions
     */
    private function assertSameCanonicalFeaturePermissions(array $permissions): void
    {
        foreach ([
            'chat.view',
            'chat.conversations.view',
            'contacts.view',
            'extensions.view',
            'phone_numbers.view',
            'call_logs.view',
            'call_logs.view_all',
            'call_logs.view_statistics',
        ] as $permission) {
            $this->assertContains($permission, $permissions);
        }

        foreach ([
            'tenant.contacts.view',
            'tenant.extensions.view',
            'tenant.phone_numbers.view',
            'tenant.call_logs.view',
        ] as $legacyName) {
            $this->assertNotContains($legacyName, $permissions);
        }
    }
}
