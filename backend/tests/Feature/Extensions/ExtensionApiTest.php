<?php

namespace Tests\Feature\Extensions;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\TestCase;

class ExtensionApiTest extends TestCase
{
    use BuildsExtensionFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'fake');
    }

    public function test_create_list_update_delete_and_rotate_credentials_are_tenant_scoped(): void
    {
        $tenantA = $this->createTenant('extensions-a');
        $tenantB = $this->createTenant('extensions-b');
        $user = $this->actingAsTenantUser($this->createUser('extensions-user'));
        $tenantAContact = $this->createContactFixture($tenantA, $user, ['display_name' => 'Tenant A Contact']);

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, [
            'extensions.view',
            'extensions.create',
            'extensions.update',
            'extensions.delete',
            'extensions.manage_credentials',
            'contacts.view',
        ]);
        $this->assignTenantPermissions($user, $tenantB, ['extensions.view']);

        $createResponse = $this->postJson('/api/v1/extensions', [
            'number' => '2001',
            'label' => 'Sales Desk',
            'assigned_user_id' => $user->id,
            'assigned_contact_id' => $tenantAContact->id,
        ], ['X-Tenant-ID' => $tenantA->id])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenantA->id)
            ->assertJsonPath('data.number', '2001')
            ->assertJsonPath('data.credential.username', '2001');

        $extensionId = $createResponse->json('data.id');
        $plainSecret = (string) $createResponse->json('data.plain_secret');
        $this->assertNotSame('', $plainSecret);

        $this->getJson('/api/v1/extensions', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.plain_secret');

        $this->getJson("/api/v1/extensions/{$extensionId}", ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonMissingPath('data.plain_secret')
            ->assertJsonPath('data.provider_state.endpoint_status', 'active');

        $rotateResponse = $this->postJson("/api/v1/extensions/{$extensionId}/rotate-credentials", [], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.id', $extensionId);

        $this->assertNotSame($plainSecret, (string) $rotateResponse->json('data.plain_secret'));
        $this->assertStringNotContainsString($plainSecret, (string) $rotateResponse->getContent());

        $this->putJson("/api/v1/extensions/{$extensionId}", [
            'number' => '2002',
            'label' => 'Support Desk',
            'status' => 'suspended',
            'assigned_contact_id' => null,
        ], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.number', '2002')
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.provider_state.endpoint_status', 'suspended');

        $this->getJson('/api/v1/extensions/assignment-options', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonCount(1, 'data.contacts');

        $this->getJson("/api/v1/extensions/{$extensionId}", ['X-Tenant-ID' => $tenantB->id])
            ->assertNotFound();

    }

    public function test_duplicate_numbers_and_cross_tenant_assignments_fail_safely(): void
    {
        $tenantA = $this->createTenant('extensions-dup-a');
        $tenantB = $this->createTenant('extensions-dup-b');
        $user = $this->actingAsTenantUser($this->createUser('extensions-dup-user'));
        $contactB = $this->createContactFixture($tenantB, $user, ['display_name' => 'Tenant B Contact']);

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['extensions.view', 'extensions.create', 'extensions.update']);
        $this->assignTenantPermissions($user, $tenantB, ['extensions.view']);

        $this->postJson('/api/v1/extensions', [
            'number' => '3001',
            'label' => 'First',
        ], ['X-Tenant-ID' => $tenantA->id])->assertCreated();

        $this->postJson('/api/v1/extensions', [
            'number' => '3001',
            'label' => 'Duplicate',
        ], ['X-Tenant-ID' => $tenantA->id])->assertStatus(409);

        $this->postJson('/api/v1/extensions', [
            'number' => '3002',
            'assigned_contact_id' => $contactB->id,
        ], ['X-Tenant-ID' => $tenantA->id])->assertStatus(409);
    }

    public function test_extension_list_stays_scoped_to_the_active_tenant(): void
    {
        $tenantA = $this->createTenant('extensions-list-a');
        $tenantB = $this->createTenant('extensions-list-b');
        $user = $this->actingAsTenantUser($this->createUser('extensions-list-user'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['extensions.view']);
        $this->assignTenantPermissions($user, $tenantB, ['extensions.view']);

        $this->createExtensionFixture($tenantA, $user, [
            'number' => '1001',
            'label' => 'Tenant-A Support',
        ]);
        $this->createExtensionFixture($tenantA, $user, [
            'number' => '1002',
            'label' => 'Tenant-A Sales',
        ]);
        $this->createExtensionFixture($tenantB, $user, [
            'number' => '2001',
            'label' => 'Tenant-B Support',
        ]);
        $this->createExtensionFixture($tenantB, $user, [
            'number' => '2002',
            'label' => 'Tenant-B Sales',
        ]);

        $this->getJson('/api/v1/extensions', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', '1001')
            ->assertJsonPath('data.1.number', '1002');

        $this->getJson('/api/v1/extensions', ['X-Tenant-ID' => $tenantB->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', '2001')
            ->assertJsonPath('data.1.number', '2002');
    }

    public function test_platform_permissions_and_suspended_membership_do_not_grant_extension_access(): void
    {
        $tenant = $this->createTenant('extensions-access');
        $user = $this->actingAsTenantUser($this->createUser('extensions-access-user'));
        $extension = $this->createExtensionFixture($tenant, $user);

        $this->createMembership($tenant, $user, \App\Enums\TenantMembershipStatus::Suspended);
        $this->assignPlatformPermissions($user, ['extensions.view']);

        $this->getJson("/api/v1/extensions/{$extension->id}", ['X-Tenant-ID' => $tenant->id])
            ->assertForbidden();
    }

    public function test_delete_removes_extension_inside_active_tenant(): void
    {
        $tenant = $this->createTenant('extensions-delete');
        $user = $this->actingAsTenantUser($this->createUser('extensions-delete-user'));

        $this->createMembership($tenant, $user);
        $this->assignTenantPermissions($user, $tenant, [
            'extensions.view',
            'extensions.delete',
        ]);

        $extension = $this->createExtensionFixture($tenant, $user);

        $this->deleteJson("/api/v1/extensions/{$extension->id}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }
}
