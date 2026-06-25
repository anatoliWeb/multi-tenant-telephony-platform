<?php

namespace Tests\Feature\Contacts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Contacts\Concerns\BuildsContactFixtures;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use BuildsContactFixtures;
    use RefreshDatabase;

    public function test_create_list_update_and_delete_contacts_are_tenant_scoped(): void
    {
        $tenantA = $this->createTenant('contacts-a');
        $tenantB = $this->createTenant('contacts-b');
        $user = $this->actingAsTenantUser($this->createUser('contacts-user'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, [
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.delete',
            'contacts.manage_tags',
            'contacts.import',
            'contacts.export',
        ]);
        $this->assignTenantPermissions($user, $tenantB, ['contacts.view']);

        $tag = $this->addContactTag($tenantA, 'VIP');

        $createResponse = $this->postJson('/api/v1/contacts', [
                'tenant_id' => $tenantB->id,
                'first_name' => 'Alice',
                'last_name' => 'Able',
                'company_name' => 'Acme',
                'phones' => [
                    ['raw_number' => '+15550101010', 'is_primary' => true],
                    ['raw_number' => '+15550101011', 'is_primary' => false],
                ],
                'emails' => [
                    ['email' => 'alice@example.test', 'is_primary' => true],
                ],
                'tag_ids' => [$tag->id],
            ], ['X-Tenant-ID' => $tenantA->id])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenantA->id)
            ->assertJsonPath('data.display_name', 'Alice Able')
            ->assertJsonCount(2, 'data.phones')
            ->assertJsonCount(1, 'data.tags');

        $contactId = $createResponse->json('data.id');

        $this->getJson('/api/v1/contacts', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/contacts/{$contactId}", [
                'display_name' => 'Alice Updated',
                'status' => 'archived',
                'phones' => [
                    ['raw_number' => '+15550101012', 'is_primary' => true],
                ],
                'tag_ids' => [$tag->id],
            ], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Alice Updated')
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.phones.0.raw_number', '+15550101012');

        $this->withHeaders(['X-Tenant-ID' => $tenantA->id])
            ->deleteJson("/api/v1/contacts/{$contactId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->getJson('/api/v1/contacts', ['X-Tenant-ID' => $tenantA->id])
            ->assertJsonPath('meta.total', 0);

        $crossTenantContactId = $this->postJson('/api/v1/contacts', [
                'display_name' => 'Tenant A Only',
                'phones' => [
                    ['raw_number' => '+15550101013', 'is_primary' => true],
                ],
            ], ['X-Tenant-ID' => $tenantA->id])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/v1/contacts', ['X-Tenant-ID' => $tenantB->id])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson("/api/v1/contacts/{$crossTenantContactId}", ['X-Tenant-ID' => $tenantB->id])
            ->assertNotFound();

        $this->patchJson("/api/v1/contacts/{$crossTenantContactId}", ['display_name' => 'Cross Tenant'], ['X-Tenant-ID' => $tenantB->id])
            ->assertNotFound();

        $this->withHeaders(['X-Tenant-ID' => $tenantB->id])
            ->deleteJson("/api/v1/contacts/{$crossTenantContactId}")
            ->assertNotFound();
    }

    public function test_duplicate_phone_and_cross_tenant_tag_assignment_fail_safely(): void
    {
        $tenantA = $this->createTenant('contacts-dup-a');
        $tenantB = $this->createTenant('contacts-dup-b');
        $user = $this->actingAsTenantUser($this->createUser('contacts-dup-user'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['contacts.view', 'contacts.create', 'contacts.manage_tags']);
        $this->assignTenantPermissions($user, $tenantB, ['contacts.view', 'contacts.create', 'contacts.manage_tags']);

        $crossTenantTag = $this->addContactTag($tenantB, 'Tenant B Only');

        $this->postJson('/api/v1/contacts', [
                'display_name' => 'First Contact',
                'phones' => [['raw_number' => '+15559990000', 'is_primary' => true]],
            ], ['X-Tenant-ID' => $tenantA->id])
            ->assertCreated();

        $this->postJson('/api/v1/contacts', [
                'display_name' => 'Duplicate Contact',
                'phones' => [['raw_number' => '+15559990000', 'is_primary' => true]],
            ], ['X-Tenant-ID' => $tenantA->id])
            ->assertStatus(409);

        $this->postJson('/api/v1/contacts', [
                'display_name' => 'Tag Conflict Contact',
                'phones' => [['raw_number' => '+15559990001', 'is_primary' => true]],
                'tag_ids' => [$crossTenantTag->id],
            ], ['X-Tenant-ID' => $tenantA->id])
            ->assertStatus(409);
    }

    public function test_platform_permission_alone_and_suspended_membership_do_not_grant_contacts_access(): void
    {
        $tenant = $this->createTenant('contacts-access');
        $user = $this->actingAsTenantUser($this->createUser('contacts-access-user'));
        $contact = $this->createContactFixture($tenant, $user);

        $this->createMembership($tenant, $user, \App\Enums\TenantMembershipStatus::Suspended);
        $this->assignPlatformPermissions($user, ['contacts.view']);

        $this->getJson("/api/v1/contacts/{$contact->id}", ['X-Tenant-ID' => $tenant->id])
            ->assertForbidden();
    }
}
