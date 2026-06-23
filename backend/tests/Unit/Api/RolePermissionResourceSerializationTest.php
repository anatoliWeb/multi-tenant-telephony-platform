<?php

namespace Tests\Unit\Api;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionResourceSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_resource_serializes_model(): void
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'description' => 'Administrator',
        ]);
        $payload = (new RoleResource($role))->resolve();

        $this->assertSame($role->id, $payload['id']);
        $this->assertSame('admin', $payload['name']);
    }

    public function test_role_resource_serializes_string_without_crashing(): void
    {
        $payload = (new RoleResource('support'))->resolve();

        $this->assertNull($payload['id']);
        $this->assertSame('support', $payload['name']);
    }

    public function test_permission_resource_serializes_mixed_input_safely(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.view',
            'description' => 'View users',
        ]);

        $fromModel = (new PermissionResource($permission))->resolve();
        $fromString = (new PermissionResource('roles.edit'))->resolve();
        $fromArray = (new PermissionResource([
            'id' => 99,
            'name' => 'tokens.view',
            'description' => 'View tokens',
        ]))->resolve();

        $this->assertSame($permission->id, $fromModel['id']);
        $this->assertSame('users.view', $fromModel['name']);

        $this->assertNull($fromString['id']);
        $this->assertSame('roles.edit', $fromString['name']);

        $this->assertSame(99, $fromArray['id']);
        $this->assertSame('tokens.view', $fromArray['name']);
        $this->assertSame('View tokens', $fromArray['description']);
    }
}
