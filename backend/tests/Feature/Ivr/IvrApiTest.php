<?php

namespace Tests\Feature\Ivr;

use App\Enums\TenantMembershipStatus;
use App\Models\IvrMenu;
use App\Models\IvrOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\Isolation\Concerns\BuildsTenantIsolationFixtures;
use Tests\TestCase;

class IvrApiTest extends TestCase
{
    use BuildsTenantIsolationFixtures;
    use RefreshDatabase;

    public function test_ivr_crud_options_and_route_testing_are_tenant_scoped(): void
    {
        $tenant = $this->createTenant('ivr-a');
        $otherTenant = $this->createTenant('ivr-b');
        $owner = $this->actingAsTenantUser($this->createUser('ivr-owner'));
        $otherUser = $this->createUser('ivr-other');

        $this->createMembership($tenant, $owner);
        $this->createMembership($otherTenant, $otherUser, TenantMembershipStatus::Active);

        $this->assignTenantPermissions($owner, $tenant, [
            'ivr.view',
            'ivr.create',
            'ivr.update',
            'ivr.delete',
            'ivr.manage_options',
            'ivr.test_route',
            'ring_groups.view',
            'call_queues.view',
            'extensions.view',
        ]);
        $this->assignTenantPermissions($owner, $otherTenant, ['ivr.view']);

        $createResponse = $this->postJson('/api/v1/ivr-menus', [
            'name' => 'Main IVR',
            'description' => 'Primary menu',
            'status' => 'active',
            'repeat_count' => 2,
            'input_timeout_seconds' => 5,
            'max_invalid_attempts' => 3,
            'timeout_action_type' => 'hangup',
            'invalid_action_type' => 'repeat',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.timeout_destination_summary', 'hangup')
            ->assertJsonPath('data.invalid_action_type', 'repeat');

        $menuId = (int) $createResponse->json('data.id');

        $this->getJson('/api/v1/ivr-menus/options', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.actions.0', 'repeat')
            ->assertJsonPath('data.destination_types.0', 'extension');

        $this->postJson("/api/v1/ivr-menus/{$menuId}/options", [
            'digit' => '1',
            'label' => 'Sales',
            'destination_type' => 'hangup',
            'priority' => 1,
            'is_active' => true,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.destination_summary', 'hangup')
            ->assertJsonPath('data.digit', '1');

        $this->getJson("/api/v1/ivr-menus/{$menuId}/options", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $routePlan = $this->postJson("/api/v1/ivr-menus/{$menuId}/test-route", [
            'input_type' => 'digit',
            'digit' => '1',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->json('data');

        $this->assertSame('hangup', $routePlan['destination']['type']);
        $this->assertSame('1', $routePlan['digit']);
        $this->assertSame('digit', $routePlan['input_type']);

        $this->putJson("/api/v1/ivr-menus/{$menuId}", [
            'description' => 'Updated menu',
            'invalid_action_type' => 'hangup',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated menu')
            ->assertJsonPath('data.invalid_action_type', 'hangup');

        $optionId = (int) IvrOption::query()->where('ivr_menu_id', $menuId)->firstOrFail()->id;

        $this->putJson("/api/v1/ivr-menus/{$menuId}/options/{$optionId}", [
            'label' => 'Sales Desk',
            'priority' => 2,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.label', 'Sales Desk')
            ->assertJsonPath('data.priority', 2);

        $this->deleteJson("/api/v1/ivr-menus/{$menuId}/options/{$optionId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson("/api/v1/ivr-menus/{$menuId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->getJson("/api/v1/ivr-menus/{$menuId}", ['X-Tenant-ID' => $otherTenant->id])
            ->assertNotFound();
    }

    public function test_ivr_access_requires_permissions_and_active_tenant_membership(): void
    {
        $tenant = $this->createTenant('ivr-access');
        $user = $this->actingAsTenantUser($this->createUser('ivr-access-user'));
        $suspendedUser = $this->actingAsTenantUser($this->createUser('ivr-suspended-user'));

        $this->createMembership($tenant, $user);
        $this->createMembership($tenant, $suspendedUser, TenantMembershipStatus::Suspended);

        $menu = IvrMenu::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Access IVR',
            'slug' => 'access-ivr',
            'description' => null,
            'status' => 'active',
            'greeting_text' => null,
            'greeting_audio_path' => null,
            'repeat_count' => 1,
            'input_timeout_seconds' => 5,
            'max_invalid_attempts' => 3,
            'timeout_action_type' => 'repeat',
            'timeout_destination_type' => null,
            'timeout_destination_id' => null,
            'invalid_action_type' => 'repeat',
            'invalid_destination_type' => null,
            'invalid_destination_id' => null,
            'settings' => [],
            'metadata' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/ivr-menus')
            ->assertForbidden();

        $this->assignTenantPermissions($user, $tenant, ['ivr.view']);
        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/ivr-menus')
            ->assertOk();

        Sanctum::actingAs($suspendedUser);
        $this->assignTenantPermissions($suspendedUser, $tenant, ['ivr.view']);

        $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson("/api/v1/ivr-menus/{$menu->id}")
            ->assertForbidden();
    }
}
