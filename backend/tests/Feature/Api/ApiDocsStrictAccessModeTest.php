<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocsStrictAccessModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
        config()->set('api-docs.local_bypass', false);
    }

    public function test_guest_cannot_access_any_docs_route_in_local_strict_mode(): void
    {
        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
        $this->get('/docs/api/portal')->assertForbidden();
        $this->get('/docs/api.filtered.json')->assertForbidden();
    }

    public function test_limited_user_can_access_portal_and_filtered_but_not_raw_docs(): void
    {
        $user = $this->userWithPermissions(['api.docs.view']);
        $this->actingAs($user);

        $this->get('/docs/api/portal')->assertOk();
        $this->get('/docs/api.filtered.json')->assertOk();
        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
    }

    public function test_full_user_can_access_all_docs_routes(): void
    {
        $user = $this->userWithPermissions(['api.docs.view', 'api.docs.view.full']);
        $this->actingAs($user);

        $this->get('/docs/api/portal')->assertOk();
        $this->get('/docs/api.filtered.json')->assertOk();
        $this->get('/docs/api')->assertOk();
        $this->get('/docs/api.json')->assertOk();
    }

    public function test_scramble_raw_docs_routes_include_api_docs_access_middleware(): void
    {
        $ui = Route::getRoutes()->getByName('scramble.docs.ui');
        $json = Route::getRoutes()->getByName('scramble.docs.document');

        $this->assertNotNull($ui);
        $this->assertNotNull($json);
        $this->assertContains('App\Http\Middleware\ApiDocsAccessMiddleware', $ui->gatherMiddleware());
        $this->assertContains('App\Http\Middleware\ApiDocsAccessMiddleware', $json->gatherMiddleware());
    }

    public function test_local_bypass_true_allows_guest_dev_access(): void
    {
        config()->set('api-docs.local_bypass', true);

        $this->get('/docs/api')->assertOk();
        $this->get('/docs/api.json')->assertOk();
        $this->get('/docs/api/portal')->assertOk();
        $this->get('/docs/api.filtered.json')->assertOk();
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $ids = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->syncWithoutDetaching($ids);

        return $user;
    }
}

