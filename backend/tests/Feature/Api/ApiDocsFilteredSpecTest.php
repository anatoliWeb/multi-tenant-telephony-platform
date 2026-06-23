<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsFilteredSpecTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_non_local_user_without_api_docs_view_cannot_access_filtered_spec(): void
    {
        $user = User::factory()->create();

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $this->get('/docs/api.filtered.json')->assertForbidden();
        });
    }

    public function test_full_access_user_gets_full_critical_paths(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view', 'api.docs.view.full']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $spec = $this->getJson('/docs/api.filtered.json')->assertOk()->json();
            $paths = array_keys((array) data_get($spec, 'paths', []));
            $serializedPaths = implode(' ', $paths);

            $this->assertStringContainsString('/users', $serializedPaths);
            $this->assertStringContainsString('/chat/conversations', $serializedPaths);
            $this->assertStringContainsString('/chat/webhook-endpoints', $serializedPaths);
            $this->assertStringContainsString('/chat/external/messages', $serializedPaths);
        });
    }

    public function test_users_only_user_sees_users_rbac_paths_but_not_chat_or_webhooks(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view', 'users.view']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $spec = $this->getJson('/docs/api.filtered.json')->assertOk()->json();
            $paths = implode(' ', array_keys((array) data_get($spec, 'paths', [])));

            $this->assertStringContainsString('/users', $paths);
            $this->assertStringContainsString('/roles', $paths);
            $this->assertStringContainsString('/permissions', $paths);
            $this->assertStringNotContainsString('/chat/conversations', $paths);
            $this->assertStringNotContainsString('/chat/webhook-endpoints', $paths);
        });
    }

    public function test_chat_user_sees_chat_paths_but_not_users_rbac_paths(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view', 'chat.conversations.view']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $spec = $this->getJson('/docs/api.filtered.json')->assertOk()->json();
            $paths = implode(' ', array_keys((array) data_get($spec, 'paths', [])));

            $this->assertStringContainsString('/chat/conversations', $paths);
            $this->assertStringNotContainsString('/users', $paths);
            $this->assertStringNotContainsString('/roles', $paths);
            $this->assertStringNotContainsString('/permissions', $paths);
        });
    }

    public function test_docs_user_without_endpoint_permissions_gets_valid_spec_with_empty_or_public_paths(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $spec = $this->getJson('/docs/api.filtered.json')->assertOk()->json();

            $this->assertNotEmpty(data_get($spec, 'openapi'));
            $this->assertNotEmpty(data_get($spec, 'info'));
            $this->assertIsArray(data_get($spec, 'paths', []));
            $this->assertNotEmpty(data_get($spec, 'components'));

            $paths = implode(' ', array_keys((array) data_get($spec, 'paths', [])));
            $this->assertStringNotContainsString('/users', $paths);
            $this->assertStringNotContainsString('/chat/conversations', $paths);
        });
    }

    public function test_filtered_spec_hides_internal_paths_and_sensitive_strings_and_portal_has_link(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['api.docs.view', 'api.docs.view.full']);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);

            $spec = $this->getJson('/docs/api.filtered.json')->assertOk()->json();
            $pathsSerialized = implode(' ', array_keys((array) data_get($spec, 'paths', [])));
            $specSerialized = strtolower((string) json_encode($spec, JSON_THROW_ON_ERROR));

            $this->assertStringNotContainsString('/broadcasting/auth', $pathsSerialized);
            $this->assertStringNotContainsString('/admin', $pathsSerialized);
            $this->assertStringNotContainsString('/telescope', $pathsSerialized);
            $this->assertStringNotContainsString('/horizon', $pathsSerialized);

            $this->assertStringNotContainsString('token_hash', $specSerialized);
            $this->assertStringNotContainsString('webhook_secret', $specSerialized);
            $this->assertStringNotContainsString('device_key', $specSerialized);
            $this->assertStringNotContainsString('raw_payload', $specSerialized);
            $this->assertStringNotContainsString('raw_response', $specSerialized);

            $this->get('/docs/api/portal')
                ->assertOk()
                ->assertSee('/docs/api.filtered.json');
        });
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        $ids = collect($permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name])->id
        )->all();

        $user->permissions()->syncWithoutDetaching($ids);
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
