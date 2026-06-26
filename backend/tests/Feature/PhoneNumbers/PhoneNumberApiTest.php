<?php

namespace Tests\Feature\PhoneNumbers;

use App\Enums\TenantMembershipStatus;
use App\Models\PhoneNumber;
use App\Services\PhoneNumbers\InboundDidResolver;
use App\Services\PhoneNumbers\UserPrimaryDidResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\PhoneNumbers\Concerns\BuildsPhoneNumberFixtures;
use Tests\TestCase;

class PhoneNumberApiTest extends TestCase
{
    use BuildsPhoneNumberFixtures;
    use DatabaseTransactions;

    public function test_phone_numbers_are_tenant_scoped_and_support_assignment_lifecycle(): void
    {
        $tenantA = $this->createTenant('phone-a');
        $tenantB = $this->createTenant('phone-b');
        $owner = $this->actingAsTenantUser($this->createUser('phone-owner'));
        $agent = $this->createUser('phone-agent');

        $this->createMembership($tenantA, $owner);
        $this->createMembership($tenantA, $agent);
        $this->createMembership($tenantB, $owner);
        $this->assignTenantPermissions($owner, $tenantA, [
            'phone_numbers.view',
            'phone_numbers.create',
            'phone_numbers.update',
            'phone_numbers.delete',
            'phone_numbers.assign',
            'phone_numbers.set_primary',
            'phone_numbers.provision',
            'phone_numbers.release',
            'extensions.view',
        ]);
        $this->assignTenantPermissions($owner, $tenantB, ['phone_numbers.view']);

        $this->createExtensionFixture($tenantA, $agent, ['number' => '2002']);

        $createResponse = $this->postJson('/api/v1/phone-numbers', [
            'number' => '+1 (555) 000-1001',
            'display_number' => '+1 555 000 1001',
            'type' => 'did',
            'status' => 'active',
            'assigned_user_id' => $owner->id,
            'is_primary' => true,
            'provider_name' => 'manual',
        ], ['X-Tenant-ID' => $tenantA->id])
            ->assertCreated()
            ->assertJsonPath('data.normalized_number', '+15550001001')
            ->assertJsonPath('data.assignment_status', 'assigned')
            ->assertJsonPath('data.is_primary', true);

        $phoneNumberId = (int) $createResponse->json('data.id');

        $this->postJson('/api/v1/phone-numbers', [
            'number' => '+15550001002',
            'display_number' => '+1 555 000 1002',
            'assigned_user_id' => $owner->id,
            'is_primary' => false,
        ], ['X-Tenant-ID' => $tenantA->id])->assertCreated();

        $this->getJson('/api/v1/phone-numbers?search=1001&assigned=assigned&primary=true', ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/users/{$owner->id}/phone-numbers", ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/users/{$owner->id}/primary-did", ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.id', $phoneNumberId);

        $secondDidId = (int) PhoneNumber::query()
            ->where('tenant_id', $tenantA->id)
            ->where('normalized_number', '+15550001002')
            ->value('id');

        $this->postJson("/api/v1/phone-numbers/{$secondDidId}/set-primary", [], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse((bool) PhoneNumber::query()->findOrFail($phoneNumberId)->is_primary);

        $this->postJson("/api/v1/phone-numbers/{$secondDidId}/assign", [
            'assigned_user_id' => $agent->id,
            'is_primary' => true,
        ], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_user.id', $agent->id)
            ->assertJsonPath('data.assigned_user.extension.number', '2002');

        $this->assertTrue((bool) PhoneNumber::query()->findOrFail($secondDidId)->fresh()->is_primary);

        $this->postJson("/api/v1/phone-numbers/{$secondDidId}/unassign", [], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.assignment_status', 'unassigned')
            ->assertJsonPath('data.is_primary', false);

        $this->postJson("/api/v1/phone-numbers/{$phoneNumberId}/release", [], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'released');

        $this->deleteJson("/api/v1/phone-numbers/{$phoneNumberId}", [], ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->getJson("/api/v1/phone-numbers/{$secondDidId}", ['X-Tenant-ID' => $tenantB->id])
            ->assertNotFound();
    }

    public function test_duplicate_numbers_are_blocked_within_tenant_but_allowed_across_tenants(): void
    {
        $tenantA = $this->createTenant('phone-dup-a');
        $tenantB = $this->createTenant('phone-dup-b');
        $user = $this->actingAsTenantUser($this->createUser('phone-dup-user'));

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['phone_numbers.view', 'phone_numbers.create']);
        $this->assignTenantPermissions($user, $tenantB, ['phone_numbers.view', 'phone_numbers.create']);

        $this->postJson('/api/v1/phone-numbers', [
            'number' => '+15550001001',
            'display_number' => '+1 555 000 1001',
        ], ['X-Tenant-ID' => $tenantA->id])->assertCreated();

        $this->postJson('/api/v1/phone-numbers', [
            'number' => '+1 (555) 000-1001',
            'display_number' => '+1 555 000 1001',
        ], ['X-Tenant-ID' => $tenantA->id])->assertStatus(409);

        $this->postJson('/api/v1/phone-numbers', [
            'number' => '+15550001001',
            'display_number' => '+1 555 000 1001',
        ], ['X-Tenant-ID' => $tenantB->id])->assertCreated();
    }

    public function test_cross_tenant_and_inactive_membership_assignments_fail_closed(): void
    {
        $tenantA = $this->createTenant('phone-access-a');
        $tenantB = $this->createTenant('phone-access-b');
        $owner = $this->actingAsTenantUser($this->createUser('phone-access-owner'));
        $tenantBUser = $this->createUser('phone-access-other');
        $suspendedUser = $this->createUser('phone-access-suspended');
        $invitedUser = $this->createUser('phone-access-invited');
        $removedUser = $this->createUser('phone-access-removed');

        $this->createMembership($tenantA, $owner);
        $this->createMembership($tenantB, $tenantBUser);
        $this->createMembership($tenantA, $suspendedUser, TenantMembershipStatus::Suspended);
        $this->createMembership($tenantA, $invitedUser, TenantMembershipStatus::Invited);
        $this->createMembership($tenantA, $removedUser, TenantMembershipStatus::Removed);
        $this->assignTenantPermissions($owner, $tenantA, ['phone_numbers.view', 'phone_numbers.assign']);

        $phoneNumber = $this->createPhoneNumberFixture($tenantA, $owner, [
            'number' => '+15550001009',
            'normalized_number' => '+15550001009',
            'assigned_user_id' => null,
        ]);

        foreach ([$tenantBUser, $suspendedUser, $invitedUser, $removedUser] as $candidate) {
            $this->postJson("/api/v1/phone-numbers/{$phoneNumber->id}/assign", [
                'assigned_user_id' => $candidate->id,
            ], ['X-Tenant-ID' => $tenantA->id])->assertStatus(409);
        }
    }

    public function test_platform_permissions_do_not_bypass_tenant_phone_number_access(): void
    {
        $tenant = $this->createTenant('phone-platform');
        $user = $this->actingAsTenantUser($this->createUser('phone-platform-user'));
        $phoneNumber = $this->createPhoneNumberFixture($tenant, $user);

        $this->createMembership($tenant, $user, TenantMembershipStatus::Suspended);
        $this->assignPlatformPermissions($user, ['phone_numbers.view']);

        $this->getJson("/api/v1/phone-numbers/{$phoneNumber->id}", ['X-Tenant-ID' => $tenant->id])
            ->assertForbidden();
    }

    public function test_primary_and_inbound_resolvers_are_tenant_aware(): void
    {
        $tenantA = $this->createTenant('resolver-a');
        $tenantB = $this->createTenant('resolver-b');
        $user = $this->createUser('resolver-user');
        $other = $this->createUser('resolver-other');

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->createMembership($tenantB, $other);

        $primaryA = $this->createPhoneNumberFixture($tenantA, $user, [
            'number' => '+15550002001',
            'normalized_number' => '+15550002001',
            'is_primary' => true,
        ]);
        $this->createPhoneNumberFixture($tenantA, $user, [
            'number' => '+15550002002',
            'normalized_number' => '+15550002002',
            'is_primary' => false,
        ]);
        $primaryB = $this->createPhoneNumberFixture($tenantB, $user, [
            'number' => '+15550002003',
            'normalized_number' => '+15550002003',
            'is_primary' => true,
        ]);
        $unassigned = $this->createPhoneNumberFixture($tenantB, $other, [
            'number' => '+15550002004',
            'normalized_number' => '+15550002004',
            'assigned_user_id' => null,
        ]);

        $resolver = app(UserPrimaryDidResolver::class);
        $inboundResolver = app(InboundDidResolver::class);

        $this->assertSame($primaryA->id, $resolver->resolve($user, $tenantA)?->id);
        $this->assertSame($primaryB->id, $resolver->resolve($user, $tenantB)?->id);
        $this->assertNull($resolver->resolve($other, $tenantA));

        $matchA = $inboundResolver->resolve('+1 555 000 2001', $tenantA);
        $this->assertNotNull($matchA);
        $this->assertSame($tenantA->id, $matchA->tenant->id);
        $this->assertSame($user->id, $matchA->assignedUser?->id);
        $this->assertTrue($matchA->routingAllowed);

        $matchB = $inboundResolver->resolve('+15550002004', $tenantB);
        $this->assertNotNull($matchB);
        $this->assertSame($unassigned->id, $matchB->phoneNumber->id);
        $this->assertFalse($matchB->routingAllowed);
    }
}
