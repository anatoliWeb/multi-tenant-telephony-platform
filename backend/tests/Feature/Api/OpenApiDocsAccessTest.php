<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OpenApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ini_set('memory_limit', '512M');
    }

    public function test_local_testing_environment_can_access_docs_routes_when_bypass_enabled(): void
    {
        config()->set('api-docs.local_bypass', true);
        $this->get('/docs/api')->assertOk();
        $this->get('/docs/api.json')->assertOk();
    }

    public function test_local_testing_environment_without_bypass_enforces_real_policy(): void
    {
        config()->set('api-docs.local_bypass', false);

        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
        $this->get('/docs/api/portal')->assertForbidden();
    }

    public function test_non_local_guest_is_denied_docs_access(): void
    {
        $this->withEnvironment('production', function (): void {
            $this->get('/docs/api')->assertForbidden();
            $this->get('/docs/api.json')->assertForbidden();
        });
    }

    public function test_non_local_authenticated_user_without_permission_is_denied(): void
    {
        $user = User::factory()->create();

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);
            $this->get('/docs/api')->assertForbidden();
            $this->get('/docs/api.json')->assertForbidden();
        });
    }

    public function test_non_local_authenticated_user_with_api_docs_permission_is_allowed(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['name' => 'api.docs.view'],
            ['description' => 'View API documentation']
        );
        $user->permissions()->syncWithoutDetaching([$permission->id]);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);

            $this->get('/docs/api/portal')->assertOk();
            $this->get('/docs/api.filtered.json')->assertOk();
            $this->get('/docs/api')->assertForbidden();
            $this->get('/docs/api.json')->assertForbidden();
        });
    }

    public function test_non_local_authenticated_user_with_full_api_docs_permission_can_access_raw_docs(): void
    {
        $user = User::factory()->create();
        $viewPermission = Permission::firstOrCreate(
            ['name' => 'api.docs.view'],
            ['description' => 'View API documentation']
        );
        $fullPermission = Permission::firstOrCreate(
            ['name' => 'api.docs.view.full'],
            ['description' => 'View full API documentation']
        );
        $user->permissions()->syncWithoutDetaching([$viewPermission->id, $fullPermission->id]);

        $this->withEnvironment('production', function () use ($user): void {
            $this->actingAs($user);

            $this->get('/docs/api')->assertOk();

            $docsJsonResponse = $this->getJson('/docs/api.json')->assertOk();
            $docsJson = json_encode($docsJsonResponse->json(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('token_hash', $docsJson);
            $this->assertStringNotContainsString('webhook_secret', $docsJson);
        });
    }

    public function test_local_strict_mode_user_with_api_docs_view_can_access_portal_and_filtered_only(): void
    {
        config()->set('api-docs.local_bypass', false);
        $user = User::factory()->create();
        $viewPermission = Permission::firstOrCreate(['name' => 'api.docs.view']);
        $user->permissions()->syncWithoutDetaching([$viewPermission->id]);

        $this->actingAs($user);
        $this->get('/docs/api/portal')->assertOk();
        $this->get('/docs/api.filtered.json')->assertOk();
        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
    }

    public function test_local_strict_mode_user_with_full_access_can_access_raw_docs(): void
    {
        config()->set('api-docs.local_bypass', false);
        $user = User::factory()->create();
        $viewPermission = Permission::firstOrCreate(['name' => 'api.docs.view']);
        $fullPermission = Permission::firstOrCreate(['name' => 'api.docs.view.full']);
        $user->permissions()->syncWithoutDetaching([$viewPermission->id, $fullPermission->id]);

        $this->actingAs($user);
        $this->get('/docs/api')->assertOk();
        $this->get('/docs/api.json')->assertOk();
    }

    public function test_gate_view_api_docs_checks_api_docs_view_permission(): void
    {
        $withoutPermission = User::factory()->create();

        $withPermission = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['name' => 'api.docs.view'],
            ['description' => 'View API documentation']
        );
        $withPermission->permissions()->syncWithoutDetaching([$permission->id]);

        $this->assertFalse(Gate::forUser($withoutPermission)->allows('viewApiDocs'));
        $this->assertTrue(Gate::forUser($withPermission)->allows('viewApiDocs'));
    }

    public function test_gate_view_full_api_docs_checks_api_docs_view_full_permission(): void
    {
        $withoutPermission = User::factory()->create();

        $withPermission = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['name' => 'api.docs.view.full'],
            ['description' => 'View full API documentation']
        );
        $withPermission->permissions()->syncWithoutDetaching([$permission->id]);

        $this->assertFalse(Gate::forUser($withoutPermission)->allows('viewFullApiDocs'));
        $this->assertTrue(Gate::forUser($withPermission)->allows('viewFullApiDocs'));
    }

    public function test_user_seeder_declares_api_docs_view_permission(): void
    {
        $seederContents = file_get_contents(database_path('seeders/UserSeeder.php'));
        $this->assertIsString($seederContents);
        $this->assertStringContainsString("'api.docs.view'", $seederContents);
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
