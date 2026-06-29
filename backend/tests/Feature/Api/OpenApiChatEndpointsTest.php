<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class OpenApiChatEndpointsTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_docs_contains_chat_endpoints_section(): void
    {
        $contents = file_get_contents(base_path('docs/api/openapi-preparation.md'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('## Chat Endpoints', $contents);
    }

    public function test_openapi_contains_critical_chat_paths_and_contract_markers(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $conversationsPath = $this->resolvePath($spec, ['/api/v1/chat/conversations', '/v1/chat/conversations', '/chat/conversations']);
        $messagesPath = $this->resolvePath($spec, ['/api/v1/chat/conversations/{conversation}/messages', '/v1/chat/conversations/{conversation}/messages', '/chat/conversations/{conversation}/messages']);
        $attachmentsUploadPath = $this->resolvePath($spec, ['/api/v1/chat/messages/{message}/attachments', '/v1/chat/messages/{message}/attachments', '/chat/messages/{message}/attachments']);
        $attachmentsDownloadPath = $this->resolvePath($spec, ['/api/v1/chat/attachments/{attachment}/download', '/v1/chat/attachments/{attachment}/download', '/chat/attachments/{attachment}/download']);
        $participantsPath = $this->resolvePath($spec, ['/api/v1/chat/conversations/{conversation}/participants', '/v1/chat/conversations/{conversation}/participants', '/chat/conversations/{conversation}/participants']);

        $this->assertNotNull($conversationsPath);
        $this->assertNotNull($messagesPath);
        $this->assertNotNull($attachmentsUploadPath);
        $this->assertNotNull($attachmentsDownloadPath);
        $this->assertNotNull($participantsPath);

        $this->assertNotEmpty(data_get($spec, "paths.{$conversationsPath}.get"));
        $this->assertNotEmpty(data_get($spec, "paths.{$messagesPath}.get"));
        $this->assertNotEmpty(data_get($spec, "paths.{$messagesPath}.post"));
        $this->assertNotEmpty(data_get($spec, "paths.{$participantsPath}.get"));

        $sendRequestBody = data_get($spec, "paths.{$messagesPath}.post.requestBody");
        $this->assertNotEmpty($sendRequestBody);

        $sendSecurity = (array) data_get($spec, "paths.{$messagesPath}.post.security", []);
        $this->assertNotEmpty($sendSecurity);

        $conversationParams = collect((array) data_get($spec, "paths.{$conversationsPath}.get.parameters", []))
            ->pluck('name')
            ->values()
            ->all();
        $this->assertContains('page', $conversationParams);
        $this->assertContains('per_page', $conversationParams);
        $this->assertContains('search', $conversationParams);
        $this->assertContains('type', $conversationParams);
        $this->assertContains('visibility', $conversationParams);
    }

    public function test_chat_paths_do_not_expose_sensitive_secret_examples(): void
    {
        $spec = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json();

        $paths = (array) data_get($spec, 'paths', []);
        $chatOnly = collect($paths)
            ->filter(fn (mixed $value, string $path): bool => str_contains($path, '/chat/'))
            ->all();

        $serialized = json_encode($chatOnly, JSON_THROW_ON_ERROR);
        $this->assertIsString($serialized);

        $lower = strtolower($serialized);
        $this->assertStringNotContainsString('token_hash', $lower);
        $this->assertStringNotContainsString('webhook_secret', $lower);
        $this->assertStringNotContainsString('authorization', $lower);
    }

    public function test_chat_route_runtime_auth_and_permission_contract(): void
    {
        $this->getJson('/api/v1/chat/conversations')
            ->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/chat/conversations')
            ->assertStatus(403);

        $user = User::factory()->create();
        $this->prepareTenantChatUser($user, ['chat.conversations.view']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/chat/conversations')
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
