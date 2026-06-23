<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiDocsAccessMiddleware;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\PermissionCacheService;
use App\Services\UserService;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_foundation_exists_in_seed_context(): void
    {
        $this->seed(UserSeeder::class);

        foreach ([
            'api.docs.view',
            'api.docs.view.full',
            'system.monitoring',
            'chat.view',
            'chat.conversations.view',
            'chat.webhooks.manage',
            'chat.admin.view_metadata',
        ] as $permissionName) {
            $this->assertDatabaseHas('permissions', ['name' => $permissionName]);
        }
    }

    public function test_admin_role_is_broad_and_effectively_full_after_seed(): void
    {
        $this->seed(UserSeeder::class);

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $totalPermissions = Permission::query()->count();
        $adminPermissions = $adminRole->permissions()->count();

        $this->assertSame($totalPermissions, $adminPermissions);
        $this->assertGreaterThan(0, $adminPermissions);
    }

    public function test_users_endpoint_enforces_401_403_200_and_safe_error_envelopes(): void
    {
        $unauthenticated = $this->getJson('/api/v1/users')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');

        $this->assertSafeContent((string) $unauthenticated->getContent());

        Sanctum::actingAs(User::factory()->create());

        $forbidden = $this->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden');

        $this->assertSafeContent((string) $forbidden->getContent());

        $authorized = $this->actingAsWithPermissions(['users.view']);
        User::factory()->count(2)->create();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);

        $this->assertSame(['users.view'], $authorized->permissions()->pluck('name')->values()->all());
    }

    public function test_or_permission_routes_allow_alternative_permissions(): void
    {
        $this->actingAsWithPermissions(['chat.view']);
        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAsWithPermissions(['chat.conversations.view']);
        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_meta_rbac_returns_safe_payload_for_authenticated_user(): void
    {
        $this->actingAsWithPermissions(['users.view']);

        $response = $this->getJson('/api/v1/meta/rbac')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'roles',
                    'permissions',
                    'role_permissions',
                ],
            ]);

        $this->assertSafeContent((string) $response->getContent());
    }

    public function test_rbac_cache_versions_bump_for_role_and_user_permission_changes_and_no_cache_flush_is_used(): void
    {
        /** @var PermissionCacheService $cacheService */
        $cacheService = app(PermissionCacheService::class);

        $globalBefore = $cacheService->globalVersion();

        $role = Role::create(['name' => 'rbac-contract-role']);
        Permission::firstOrCreate(['name' => 'users.view']);
        $actor = User::factory()->create();
        $this->actingAs($actor, 'web');

        app(\App\Services\RoleService::class)->update($role, [
            'permissions' => ['users.view'],
        ]);

        $this->assertGreaterThan($globalBefore, $cacheService->globalVersion());

        $targetUser = User::factory()->create();
        $operator = User::factory()->create();
        $role->permissions()->sync([Permission::firstOrCreate(['name' => 'users.edit'])->id]);

        $userVersionBefore = $cacheService->userVersion((int) $targetUser->id);
        $this->actingAs($operator, 'web');

        /** @var UserService $userService */
        $userService = app(UserService::class);
        $userService->update($targetUser->id, [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'roles' => [$role->id],
            'permissions' => ['users.view'],
            'denied_permissions' => [],
        ]);

        $this->assertGreaterThan($userVersionBefore, $cacheService->userVersion((int) $targetUser->id));

        $cacheServiceContents = (string) file_get_contents(app_path('Services/Rbac/PermissionCacheService.php'));
        $this->assertStringNotContainsString('Cache::flush(', $cacheServiceContents);
    }

    public function test_route_middleware_contract_for_rbac_and_docs_access(): void
    {
        $this->assertRouteHasMiddleware('api.v1.users.index', 'auth:sanctum');
        $this->assertRouteHasMiddleware('api.v1.users.index', 'permission:users.view');
        $this->assertRouteHasMiddleware('api.v1.roles.index', 'permission:roles.view');
        $this->assertRouteHasMiddleware('api.v1.permissions.index', 'permission:permissions.view');
        $this->assertRouteHasMiddleware('api.v1.chat.conversations.index', 'permission:chat.view|chat.conversations.view');

        $docsPortal = Route::getRoutes()->getByName('docs.api.portal');
        $docsFiltered = Route::getRoutes()->getByName('docs.api.filtered');
        $this->assertNotNull($docsPortal);
        $this->assertNotNull($docsFiltered);

        $this->assertContains(ApiDocsAccessMiddleware::class, $docsPortal->middleware());
        $this->assertContains(ApiDocsAccessMiddleware::class, $docsFiltered->middleware());

        $withoutPermission = User::factory()->create();
        $withViewPermission = User::factory()->create();
        $withFullPermission = User::factory()->create();

        $withViewPermission->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['name' => 'api.docs.view'])->id,
        ]);
        $withFullPermission->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['name' => 'api.docs.view.full'])->id,
        ]);

        $this->assertFalse(Gate::forUser($withoutPermission)->allows('viewApiDocs'));
        $this->assertTrue(Gate::forUser($withViewPermission)->allows('viewApiDocs'));
        $this->assertFalse(Gate::forUser($withoutPermission)->allows('viewFullApiDocs'));
        $this->assertTrue(Gate::forUser($withFullPermission)->allows('viewFullApiDocs'));
    }

    public function test_role_validation_error_envelope_is_safe(): void
    {
        $this->actingAsWithPermissions(['roles.create']);

        $response = $this->postJson('/api/v1/roles', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);

        $this->assertSafeContent((string) $response->getContent());
    }

    private function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    private function assertRouteHasMiddleware(string $routeName, string $expectedMiddleware): void
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route [{$routeName}] is missing.");
        $this->assertContains($expectedMiddleware, $route->middleware());
    }

    private function assertSafeContent(string $content): void
    {
        $lower = mb_strtolower($content);
        foreach ([
            'token',
            'password',
            'secret',
            'trace',
            'authorization',
            'rbac:effective_permissions:version',
            'rbac:user:effective_permissions:version',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $lower);
        }
    }
}

