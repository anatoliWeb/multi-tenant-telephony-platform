<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsPermissionAwareModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_non_local_user_without_api_docs_view_permission_cannot_access_portal(): void
    {
        $user = User::factory()->create();

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $this->get('/docs/api/portal')->assertForbidden();
        });
    }

    public function test_non_local_user_with_api_docs_view_permission_can_access_portal(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $this->get('/docs/api/portal')
                ->assertOk()
                ->assertSee('API Documentation')
                ->assertSee('Open filtered API spec')
                ->assertDontSee('Open Swagger UI');
        });
    }

    public function test_portal_shows_or_hides_groups_based_on_permissions(): void
    {
        $withUsersGroup = User::factory()->create();
        $this->grantPermissions($withUsersGroup, ['api.docs.view', 'users.view']);

        $withoutUsersGroup = User::factory()->create();
        $this->grantPermissions($withoutUsersGroup, ['api.docs.view']);

        $withChatGroup = User::factory()->create();
        $this->grantPermissions($withChatGroup, ['api.docs.view', 'chat.conversations.view']);

        $withoutChatGroup = User::factory()->create();
        $this->grantPermissions($withoutChatGroup, ['api.docs.view']);

        $this->withEnvironment('production', function () use ($withUsersGroup, $withoutUsersGroup, $withChatGroup, $withoutChatGroup): void {
            $this->actingAs($withUsersGroup);
            $this->get('/docs/api/portal')->assertOk()->assertSee('Users & RBAC');

            $this->actingAs($withoutUsersGroup);
            $this->get('/docs/api/portal')->assertOk()->assertDontSee('Users & RBAC');

            $this->actingAs($withChatGroup);
            $this->get('/docs/api/portal')->assertOk()->assertSee('Chat');

            $this->actingAs($withoutChatGroup);
            $this->get('/docs/api/portal')->assertOk()->assertDontSee('Chat');
        });
    }

    public function test_full_access_user_sees_all_groups(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view', 'api.docs.view.full']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $response = $this->get('/docs/api/portal')->assertOk();

            foreach ([
                'Auth',
                'Users & RBAC',
                'Dashboard & Stats',
                'Notifications',
                'Chat',
                'Webhooks',
                'External API',
            ] as $label) {
                $response->assertSee($label);
            }
        });
    }

    public function test_docs_user_with_no_endpoint_permissions_gets_safe_empty_state(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $this->get('/docs/api/portal')
                ->assertOk()
                ->assertSee('No available API groups')
                ->assertSee('no API groups are available for your current permissions');
        });
    }

    public function test_portal_does_not_expose_internal_routes_or_secrets(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view', 'api.docs.view.full']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $content = (string) $this->get('/docs/api/portal')->assertOk()->getContent();

            $this->assertStringNotContainsString('/broadcasting/auth', $content);
            $this->assertStringNotContainsString('/admin/dashboard', $content);
            $this->assertStringNotContainsString('/telescope', $content);
            $this->assertStringNotContainsString('/horizon', $content);
            $this->assertStringNotContainsString('token_hash', $content);
            $this->assertStringNotContainsString('webhook_secret', $content);
            $this->assertStringNotContainsString('signature', $content);
        });
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionIds = collect($permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name])->id
        )->all();

        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function withEnvironment(string $environment, callable $callback): void
    {
        $originalEnvironment = app()->environment();
        $this->app->detectEnvironment(fn () => $environment);

        try {
            $callback();
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
