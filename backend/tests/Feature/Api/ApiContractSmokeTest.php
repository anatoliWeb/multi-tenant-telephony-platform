<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiContractSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_api_health_endpoints_return_safe_liveness_contracts(): void
    {
        $public = $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $api = $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');

        $this->assertSafeResponseContent($public->getContent());
        $this->assertSafeResponseContent($api->getContent());
    }

    public function test_meta_bootstrap_requires_authentication_and_allows_authenticated_users(): void
    {
        $this->getJson('/api/v1/meta/bootstrap')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/meta/bootstrap')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_user.id', $user->id)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_user',
                    'current_user_permissions',
                ],
            ]);
    }

    public function test_core_rbac_endpoints_enforce_auth_permissions_and_success_envelopes(): void
    {
        Role::create(['name' => 'contract-role']);
        Permission::firstOrCreate(['name' => 'contract.permission']);
        User::factory()->count(2)->create();

        $this->getJson('/api/v1/users')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden');

        $this->actingAsWithPermissions([
            'users.view',
            'roles.view',
            'permissions.view',
        ]);

        foreach (['/api/v1/users', '/api/v1/roles', '/api/v1/permissions'] as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['success', 'message', 'data']);
        }
    }

    public function test_dashboard_activity_notifications_settings_and_translations_contracts(): void
    {
        $user = $this->actingAsWithPermissions([
            'activity.view',
            'notifications.view',
            'settings.view',
            'translations.view',
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'api_contract_smoke',
            'description' => 'API contract smoke event',
            'meta' => [],
        ]);

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);

        $this->getJson('/api/v1/activity?per_page=5')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        foreach (['/api/v1/notifications', '/api/v1/settings'] as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['success', 'message', 'data']);
        }

        $this->getJson('/api/v1/translations?frontend=1')
            ->assertOk()
            ->assertJsonStructure(['locale', 'translations']);

        $this->getJson('/api/v1/translations/manage')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);
    }

    public function test_validation_and_not_found_errors_are_standardized_and_safe(): void
    {
        $this->actingAsWithPermissions(['users.create']);

        $validation = $this->postJson('/api/v1/users', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertSafeResponseContent($validation->getContent());

        $this->actingAsWithPermissions(['users.view']);

        $notFound = $this->getJson('/api/v1/users/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Endpoint not found')
            ->assertJsonStructure(['success', 'message', 'errors']);

        $this->assertSafeResponseContent($notFound->getContent());
    }

    public function test_docs_portal_and_filtered_spec_use_permission_aware_access_contract(): void
    {
        config()->set('api-docs.local_bypass', false);

        $user = $this->userWithWebPermissions(['api.docs.view']);

        $this->actingAs($user);

        $this->get('/docs/api/portal')->assertOk();
        $this->getJson('/docs/api.filtered.json')->assertOk();
        $this->get('/docs/api')->assertForbidden();
        $this->getJson('/docs/api.json')->assertForbidden();
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

    private function userWithWebPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->sync($permissionIds);

        return $user;
    }

    private function assertSafeResponseContent(string $content): void
    {
        foreach ([
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'token_hash',
            'webhook_secret',
            'raw_payload',
            'raw_response',
            'Authorization',
            'Trace',
            '/var/www',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $content);
        }
    }
}
