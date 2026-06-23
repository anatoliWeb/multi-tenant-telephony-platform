<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiDocsPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsPermissionMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_config_contains_required_docs_groups_and_structure(): void
    {
        $groups = config('api-docs.groups');

        $this->assertIsArray($groups);

        foreach ([
            'auth',
            'users_rbac',
            'dashboard_stats',
            'notifications',
            'chat',
            'webhooks',
            'external_api',
        ] as $groupKey) {
            $this->assertArrayHasKey($groupKey, $groups);
            $this->assertIsString((string) data_get($groups, "{$groupKey}.label"));
            $this->assertIsArray(data_get($groups, "{$groupKey}.paths"));

            $isPublic = (bool) data_get($groups, "{$groupKey}.public", false);
            $hasAny = count((array) data_get($groups, "{$groupKey}.permissions_any", [])) > 0;
            $hasAll = count((array) data_get($groups, "{$groupKey}.permissions_all", [])) > 0;

            $this->assertTrue(
                $isPublic || $hasAny || $hasAll,
                "Group [{$groupKey}] must declare public=true or permission rules."
            );
        }
    }

    public function test_api_docs_view_full_permission_exists_in_seed_contract(): void
    {
        $seederContents = file_get_contents(database_path('seeders/UserSeeder.php'));
        $this->assertIsString($seederContents);
        $this->assertStringContainsString("'api.docs.view.full'", $seederContents);
    }

    public function test_user_with_full_docs_permission_can_see_all_groups(): void
    {
        $service = app(ApiDocsPermissionService::class);

        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['name' => 'api.docs.view.full'],
            ['description' => 'View full API documentation']
        );
        $user->permissions()->syncWithoutDetaching([$permission->id]);

        foreach (array_keys($service->groups()) as $groupKey) {
            $this->assertTrue($service->userCanSeeGroup($user, $groupKey));
        }
    }

    public function test_permission_mapped_group_visibility_rules_work(): void
    {
        $service = app(ApiDocsPermissionService::class);

        $usersViewer = User::factory()->create();
        $usersPermission = Permission::firstOrCreate(['name' => 'users.view']);
        $usersViewer->permissions()->syncWithoutDetaching([$usersPermission->id]);
        $this->assertTrue($service->userCanSeeGroup($usersViewer, 'users_rbac'));

        $withoutUsersRbac = User::factory()->create();
        $this->assertFalse($service->userCanSeeGroup($withoutUsersRbac, 'users_rbac'));

        $chatViewer = User::factory()->create();
        $chatPermission = Permission::firstOrCreate(['name' => 'chat.conversations.view']);
        $chatViewer->permissions()->syncWithoutDetaching([$chatPermission->id]);
        $this->assertTrue($service->userCanSeeGroup($chatViewer, 'chat'));

        $withoutChat = User::factory()->create();
        $this->assertFalse($service->userCanSeeGroup($withoutChat, 'chat'));
    }

    public function test_public_auth_group_and_path_matching_behavior(): void
    {
        $service = app(ApiDocsPermissionService::class);

        $docsUser = User::factory()->create();
        $this->assertTrue($service->userCanSeeGroup($docsUser, 'auth'));
        $this->assertTrue($service->userCanSeePath($docsUser, '/api/v1/auth/session/login'));
        $this->assertFalse($service->userCanSeePath($docsUser, '/api/v1/unknown/not-mapped'));

        $admin = User::factory()->create();
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'description' => 'Administrator',
        ]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->assertTrue($service->userHasFullDocsAccess($admin));
        $this->assertTrue($service->userCanSeePath($admin, '/api/v1/unknown/not-mapped'));
    }

    public function test_docs_permission_map_does_not_include_internal_routes(): void
    {
        $groupsJson = json_encode(config('api-docs.groups'), JSON_THROW_ON_ERROR);
        $this->assertIsString($groupsJson);

        $this->assertStringNotContainsString('/broadcasting/auth', $groupsJson);
        $this->assertStringNotContainsString('/admin', $groupsJson);
        $this->assertStringNotContainsString('/telescope', $groupsJson);
        $this->assertStringNotContainsString('/horizon', $groupsJson);
    }

    public function test_openapi_docs_mention_permission_aware_docs_map(): void
    {
        $docs = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($docs);
        $this->assertStringContainsString('## Permission-Aware API Documentation', $docs);
        $this->assertStringContainsString('config/api-docs.php', $docs);
    }
}
