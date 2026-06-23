<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsPortalI18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_portal_renders_english_labels_by_default(): void
    {
        $user = $this->docsUserWithPermissions(['users.view']);

        $response = $this->actingAs($user)->get('/docs/api/portal')->assertOk();
        $response->assertSee('API Documentation');
        $response->assertSee('Users & RBAC');
        $response->assertSee('Limited access');
    }

    public function test_portal_renders_ukrainian_labels_for_uk_locale_query(): void
    {
        $user = $this->docsUserWithPermissions(['chat.conversations.view']);

        $response = $this->actingAs($user)
            ->get('/docs/api/portal?lang=uk')
            ->assertOk();

        $response->assertSee('API Документація');
        $response->assertSee('Чат');
        $response->assertSee('Обмежений доступ');
    }

    public function test_portal_renders_german_labels_for_de_locale_query(): void
    {
        $user = $this->docsUserWithPermissions(['api.docs.view.full']);

        $response = $this->actingAs($user)
            ->get('/docs/api/portal?lang=de')
            ->assertOk();

        $response->assertSee('API-Dokumentation');
        $response->assertSee('Benutzer & RBAC');
        $response->assertSee('Vollzugriff');
    }

    public function test_missing_translation_falls_back_to_english_or_config_label(): void
    {
        $user = $this->docsUserWithPermissions(['api.docs.view.full']);

        $response = $this->actingAs($user)->get('/docs/api/portal?lang=fr')->assertOk();
        $response->assertSee('API Documentation');
        $response->assertSee('Users & RBAC');
    }

    public function test_portal_has_language_switcher_links(): void
    {
        $user = $this->docsUserWithPermissions(['users.view']);

        $response = $this->actingAs($user)->get('/docs/api/portal')->assertOk();
        $response->assertSee('/docs/api/portal?lang=en');
        $response->assertSee('/docs/api/portal?lang=uk');
        $response->assertSee('/docs/api/portal?lang=de');
    }

    public function test_no_access_state_and_sensitive_data_safety(): void
    {
        $user = $this->docsUserWithPermissions([]);

        $content = (string) $this->actingAs($user)->get('/docs/api/portal')->assertOk()->getContent();
        $this->assertStringContainsString('No endpoint access', $content);
        $this->assertStringNotContainsString('token_hash', $content);
        $this->assertStringNotContainsString('webhook_secret', $content);
        $this->assertStringNotContainsString('signature', strtolower($content));
    }

    public function test_limited_user_sees_filtered_link_but_not_raw_swagger_links(): void
    {
        $user = $this->docsUserWithPermissions(['users.view']);

        $response = $this->actingAs($user)->get('/docs/api/portal')->assertOk();
        $response->assertSee('/docs/api.filtered.json');
        $response->assertDontSee('/docs/api.json');
        $response->assertDontSee('Open Swagger UI');
    }

    public function test_full_access_user_sees_raw_swagger_and_raw_json_links(): void
    {
        $user = $this->docsUserWithPermissions(['api.docs.view.full']);

        $response = $this->actingAs($user)->get('/docs/api/portal')->assertOk();
        $response->assertSee('/docs/api');
        $response->assertSee('/docs/api.json');
        $response->assertSee('Open Swagger UI');
    }

    private function docsUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $required = array_merge(['api.docs.view'], $permissions);

        $ids = collect($required)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->syncWithoutDetaching($ids);

        return $user;
    }
}

