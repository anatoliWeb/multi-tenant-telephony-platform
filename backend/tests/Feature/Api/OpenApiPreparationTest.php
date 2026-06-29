<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\Chat\ChatIncomingWebhookController;
use App\Http\Controllers\Api\V1\Chat\ChatMessageController;
use App\Http\Requests\Api\SendChatMessageRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class OpenApiPreparationTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $chatPermissions = array_values(array_filter($permissions, static fn (string $name): bool => str_starts_with($name, 'chat.')));
        $platformPermissions = array_values(array_diff($permissions, $chatPermissions));

        if ($chatPermissions !== []) {
            $this->prepareTenantChatUser($user, $chatPermissions);
        }

        if ($platformPermissions !== []) {
            $permissionIds = collect($platformPermissions)
                ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'scope' => 'platform'])->id)
                ->all();
            $user->permissions()->syncWithoutDetaching($permissionIds);
        }

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_critical_v1_routes_exist_and_have_names(): void
    {
        $criticalRouteNames = [
            'api.v1.auth.login',
            'api.v1.auth.token',
            'api.v1.auth.me',
            'api.v1.meta',
            'api.v1.meta.bootstrap',
            'api.v1.meta.rbac',
            'api.v1.chat.conversations.index',
            'api.v1.chat.messages.store',
            'api.v1.chat.external.messages.store',
            'api.v1.chat.external.webhooks.handle',
            'api.v1.chat.conversations.webhook-deliveries.index',
        ];

        foreach ($criticalRouteNames as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Route [{$name}] must exist.");
        }
    }

    public function test_protected_routes_include_auth_middleware(): void
    {
        $protectedRouteNames = [
            'api.v1.meta',
            'api.v1.meta.bootstrap',
            'api.v1.meta.rbac',
            'api.v1.chat.conversations.index',
            'api.v1.chat.messages.store',
            'api.v1.notifications.unread-count',
        ];

        foreach ($protectedRouteNames as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "Route [{$name}] must include auth:sanctum.");
        }
    }

    public function test_chat_send_route_contract_for_openapi_preparation(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.chat.messages.store');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('permission:chat.send', $middleware);
        $this->assertContains('throttle:chat-message-send', $middleware);

        $method = new ReflectionMethod(ChatMessageController::class, 'store');
        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertSame(SendChatMessageRequest::class, $parameters[0]->getType()?->getName());
    }

    public function test_external_message_and_incoming_webhook_routes_have_expected_security_middleware(): void
    {
        $externalMessageRoute = Route::getRoutes()->getByName('api.v1.chat.external.messages.store');
        $this->assertNotNull($externalMessageRoute);
        $externalMessageMiddleware = $externalMessageRoute->gatherMiddleware();
        $this->assertContains('throttle:chat-external-api', $externalMessageMiddleware);
        $this->assertContains('external.chat.scope:chat.external.messages.send', $externalMessageMiddleware);

        $incomingWebhookRoute = Route::getRoutes()->getByName('api.v1.chat.external.webhooks.handle');
        $this->assertNotNull($incomingWebhookRoute);
        $incomingWebhookMiddleware = $incomingWebhookRoute->gatherMiddleware();
        $this->assertContains('throttle:chat-external-api', $incomingWebhookMiddleware);
        $this->assertNotContains('auth:sanctum', $incomingWebhookMiddleware);

        $incomingWebhookMethod = new ReflectionMethod(ChatIncomingWebhookController::class, 'handle');
        $this->assertSame('handle', $incomingWebhookMethod->getName());
    }

    public function test_paginated_endpoint_and_validation_error_envelope_are_standardized(): void
    {
        $this->actingAsWithPermissions(['activity.view', 'chat.create', 'chat.conversations.create']);

        $this->getJson('/api/v1/activity')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->postJson('/api/v1/chat/conversations/group', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);
    }
}
