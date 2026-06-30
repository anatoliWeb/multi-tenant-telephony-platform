<?php

namespace Tests\Feature\CallControl;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Extensions\Concerns\BuildsExtensionFixtures;
use Tests\TestCase;

class CallControlApiTest extends TestCase
{
    use BuildsExtensionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telephony.enabled', true);
        config()->set('telephony.default_provider', 'fake');
    }

    public function test_sip_profile_is_tenant_scoped_and_omits_secrets_in_normal_mode(): void
    {
        $tenantA = $this->createTenant('call-control-a');
        $tenantB = $this->createTenant('call-control-b');
        $user = $this->actingAsTenantUser($this->createUser('call-control-user'));
        $extension = $this->createExtensionFixture($tenantA, $user, [
            'number' => '2010',
            'label' => 'Softphone Desk',
        ]);

        $this->createMembership($tenantA, $user);
        $this->createMembership($tenantB, $user);
        $this->assignTenantPermissions($user, $tenantA, ['call_control.view']);
        $this->assignTenantPermissions($user, $tenantB, ['call_control.view']);

        $this->getJson("/api/v1/extensions/{$extension->id}/sip-profile", ['X-Tenant-ID' => $tenantA->id])
            ->assertOk()
            ->assertJsonPath('data.extension_id', $extension->id)
            ->assertJsonPath('data.extension_number', '2010')
            ->assertJsonPath('data.authorization_username', '2010')
            ->assertJsonPath('data.provider', 'freeswitch')
            ->assertJsonPath('data.registration.enabled', false)
            ->assertJsonMissingPath('data.authorization_password');

        $this->getJson("/api/v1/extensions/{$extension->id}/sip-profile", ['X-Tenant-ID' => $tenantB->id])
            ->assertNotFound();
    }

    public function test_sip_profile_rejects_missing_permission_inactive_extensions_and_missing_tenant_context(): void
    {
        $tenant = $this->createTenant('call-control-guards');
        $user = $this->actingAsTenantUser($this->createUser('call-control-guards-user'));
        $noAccessUser = $this->actingAsTenantUser($this->createUser('call-control-no-access'));
        $activeExtension = $this->createExtensionFixture($tenant, $user, [
            'number' => '2020',
            'label' => 'Active Softphone',
        ]);
        $inactiveExtension = $this->createExtensionFixture($tenant, $user, [
            'number' => '2021',
            'label' => 'Inactive Softphone',
            'status' => 'suspended',
        ]);

        $this->createMembership($tenant, $user);
        $this->createMembership($tenant, $noAccessUser);
        $this->assignTenantPermissions($user, $tenant, ['call_control.view']);

        $this->actingAs($noAccessUser, 'sanctum');
        $this->getJson("/api/v1/extensions/{$activeExtension->id}/sip-profile", ['X-Tenant-ID' => $tenant->id])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum');

        $this->getJson("/api/v1/extensions/{$activeExtension->id}/sip-profile")
            ->assertForbidden();

        $this->getJson("/api/v1/extensions/{$inactiveExtension->id}/sip-profile", ['X-Tenant-ID' => $tenant->id])
            ->assertForbidden();
    }
}
