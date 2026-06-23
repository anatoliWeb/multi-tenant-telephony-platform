<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenApiValidationErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_request_endpoint_returns_standardized_422_validation_shape(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'chat.conversations.create']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chat/conversations/group', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $firstFieldMessages = array_values($errors)[0] ?? null;
        $this->assertIsArray($firstFieldMessages);
        $this->assertNotEmpty($firstFieldMessages);
        $this->assertIsString($firstFieldMessages[0]);

        $raw = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('token', strtolower($raw));
        $this->assertStringNotContainsString('secret', strtolower($raw));
        $this->assertStringNotContainsString('trace', strtolower($raw));
    }

    public function test_docs_file_contains_validation_error_response_format_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## Validation Error Response Format', $contents);
    }

    public function test_openapi_contains_validation_error_response_schema_with_expected_structure(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $schema = (array) data_get($spec, 'components.schemas.ValidationErrorResponse', []);
        $this->assertNotEmpty($schema);
        $this->assertSame('object', data_get($schema, 'type'));
        $this->assertEqualsCanonicalizing(
            ['success', 'message', 'errors'],
            (array) data_get($schema, 'required', [])
        );
        $this->assertSame(['boolean'], (array) data_get($schema, 'properties.success.type'));
        $this->assertSame('string', data_get($schema, 'properties.message.type'));
        $this->assertSame('Validation failed', data_get($schema, 'properties.message.example'));
        $this->assertSame('object', data_get($schema, 'properties.errors.type'));
        $this->assertSame('array', data_get($schema, 'properties.errors.additionalProperties.type'));
        $this->assertSame('string', data_get($schema, 'properties.errors.additionalProperties.items.type'));
    }
}
