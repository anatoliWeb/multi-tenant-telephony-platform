<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenApiQueryParametersTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_contains_pagination_filtering_sorting_and_search_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## Pagination, Filtering, Sorting and Search', $contents);
    }

    public function test_openapi_json_contains_query_parameters_for_key_endpoints(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $conversationPath = $this->resolvePath($spec, [
            '/api/v1/chat/conversations',
            '/v1/chat/conversations',
            '/chat/conversations',
        ]);
        $this->assertNotNull($conversationPath);

        $conversationGet = (array) data_get($spec, "paths.{$conversationPath}.get", []);
        $conversationParams = collect((array) data_get($conversationGet, 'parameters', []))->pluck('name')->values()->all();

        $this->assertContains('page', $conversationParams);
        $this->assertContains('per_page', $conversationParams);
        $this->assertContains('search', $conversationParams);
        $this->assertContains('type', $conversationParams);
        $this->assertContains('visibility', $conversationParams);
        $this->assertContains('unread', $conversationParams);

        $usersPath = $this->resolvePath($spec, [
            '/api/v1/users',
            '/v1/users',
            '/users',
        ]);
        $this->assertNotNull($usersPath);

        $usersGet = (array) data_get($spec, "paths.{$usersPath}.get", []);
        $usersParams = collect((array) data_get($usersGet, 'parameters', []))->pluck('name')->values()->all();
        $this->assertContains('search', $usersParams);
        $this->assertContains('sort', $usersParams);
        $this->assertContains('direction', $usersParams);
        $this->assertContains('page', $usersParams);
        $this->assertContains('per_page', $usersParams);
    }

    public function test_chat_conversation_runtime_accepts_page_and_per_page_with_paginated_meta(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'chat.conversations.view']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/chat/conversations?page=1&per_page=5')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_chat_conversation_runtime_accepts_search_and_type_filters_without_server_error(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'chat.conversations.view']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/chat/conversations?search=test&type=group&visibility=private&unread=true')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function resolvePath(array $spec, array $candidates): ?string
    {
        $paths = (array) data_get($spec, 'paths', []);
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $paths)) {
                return $candidate;
            }
        }

        return null;
    }
}

