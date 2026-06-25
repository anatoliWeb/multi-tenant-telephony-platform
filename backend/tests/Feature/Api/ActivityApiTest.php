<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Enums\Rbac\PermissionScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(
                ['name' => $name, 'scope' => PermissionScope::Platform->value],
                ['scope_reference' => PermissionScope::Platform->value]
            )->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_v1_activity_endpoint(): void
    {
        $this->getJson('/api/v1/activity')->assertUnauthorized();
    }

    public function test_activity_index_requires_activity_view_permission(): void
    {
        $this->actingAsWithPermissions([]);

        $this->getJson('/api/v1/activity')->assertForbidden();
    }

    public function test_activity_index_returns_paginated_data_when_authorized(): void
    {
        $actor = $this->actingAsWithPermissions(['activity.view']);

        foreach (range(1, 12) as $index) {
            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => 'user_updated',
                'description' => "Row {$index}",
                'meta' => [],
            ]);
        }

        $expectedTotal = ActivityLog::query()->count();

        $response = $this->getJson('/api/v1/activity?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'user_id', 'action', 'description', 'meta', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', $expectedTotal);
    }

    public function test_activity_index_filters_by_action(): void
    {
        $actor = $this->actingAsWithPermissions(['activity.view']);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => 'user_created',
            'description' => 'User created',
            'meta' => [],
        ]);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => 'token_deleted',
            'description' => 'Token deleted',
            'meta' => [],
        ]);

        $response = $this->getJson('/api/v1/activity?action=token_deleted');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'token_deleted');
    }

    public function test_activity_index_filters_by_user_id(): void
    {
        $this->actingAsWithPermissions(['activity.view']);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ActivityLog::create([
            'user_id' => $userA->id,
            'action' => 'user_updated',
            'description' => 'A updated',
            'meta' => [],
        ]);

        ActivityLog::create([
            'user_id' => $userB->id,
            'action' => 'user_updated',
            'description' => 'B updated',
            'meta' => [],
        ]);

        $response = $this->getJson('/api/v1/activity?user_id=' . $userA->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $userA->id);
    }

    public function test_activity_index_supports_search_and_subject_type_filters(): void
    {
        $actor = $this->actingAsWithPermissions(['activity.view']);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => 'settings_updated',
            'description' => 'Updated billing settings',
            'meta' => ['subject_type' => 'settings'],
        ]);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => 'user_created',
            'description' => 'Created support user',
            'meta' => ['subject_type' => 'users'],
        ]);

        $search = $this->getJson('/api/v1/activity?search=billing');
        $search->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'settings_updated');

        $subject = $this->getJson('/api/v1/activity?subject_type=users');
        $subject->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'user_created');
    }
}
