<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenApiResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_endpoint_returns_standardized_success_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);
    }

    public function test_paginated_endpoint_returns_meta_shape(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'activity.view']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/activity')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_validation_error_returns_standardized_envelope(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'chat.conversations.create']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/chat/conversations/group', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);
    }

    public function test_unauthenticated_returns_standardized_error_envelope(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated')
            ->assertJsonStructure(['errors']);
    }

    public function test_forbidden_returns_standardized_error_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden')
            ->assertJsonStructure(['errors']);
    }

    public function test_openapi_json_contains_common_response_schema_candidates(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $schemas = (array) data_get($spec, 'components.schemas', []);
        $this->assertArrayHasKey('ApiSuccessResponse', $schemas);
        $this->assertArrayHasKey('ApiErrorResponse', $schemas);
        $this->assertArrayHasKey('ValidationErrorResponse', $schemas);
        $this->assertArrayHasKey('PaginatedResponse', $schemas);
        $this->assertArrayHasKey('PaginationMeta', $schemas);
    }

    public function test_openapi_preparation_docs_contains_common_response_envelope_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('## Common Response Envelope', $contents);
    }
}

