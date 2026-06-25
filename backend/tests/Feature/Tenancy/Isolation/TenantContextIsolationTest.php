<?php

namespace Tests\Feature\Tenancy\Isolation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class TenantContextIsolationTest extends TestCase
{
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_switch_tenant_does_not_distinguish_unknown_from_inaccessible_identifiers(): void
    {
        $user = $this->actingAsTenantUser($this->createUser('tenant-switcher'));
        $accessibleTenant = $this->createTenant('switch-accessible');
        $hiddenTenant = $this->createTenant('switch-hidden');

        $this->createMembership($accessibleTenant, $user);

        $inaccessibleResponse = $this->postJson('/api/v1/user/tenant/switch', [
            'tenant_uuid' => $hiddenTenant->id,
        ]);

        $unknownResponse = $this->postJson('/api/v1/user/tenant/switch', [
            'tenant_uuid' => (string) Str::uuid(),
        ]);

        foreach ([$inaccessibleResponse, $unknownResponse] as $response) {
            $response->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Tenant access denied');
        }
    }
}
