<?php

namespace Tests\Feature\Chat;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Chat\Concerns\InteractsWithTenantScopedChat;
use Tests\TestCase;

class ChatExternalApiRateLimitTest extends TestCase
{
    use InteractsWithTenantScopedChat;
    use RefreshDatabase;

    private function actingAsWithPermissions(array $permissions): User
    {
        return $this->actingAsTenantChatUser($permissions);
    }

    public function test_chat_external_api_rate_limit_foundation(): void
    {
        $routeNames = [
            'api.v1.chat.webhook-endpoints.index',
            'api.v1.chat.webhook-endpoints.store',
            'api.v1.chat.webhook-endpoints.update',
            'api.v1.chat.webhook-endpoints.destroy',
        ];

        foreach ($routeNames as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('throttle:chat-webhook-management', $route->gatherMiddleware());
        }

        config()->set('chat.webhooks.endpoint_management_rate_limit.max_attempts', 2);
        config()->set('chat.webhooks.endpoint_management_rate_limit.decay_seconds', 60);

        $this->actingAsWithPermissions(['chat.webhooks.view']);

        $r1 = $this->getJson('/api/v1/chat/webhook-endpoints');
        $r2 = $this->getJson('/api/v1/chat/webhook-endpoints');
        $r3 = $this->getJson('/api/v1/chat/webhook-endpoints');

        $this->assertNotSame(429, $r1->status());
        $this->assertNotSame(429, $r2->status());
        $r3->assertStatus(429);
        $this->assertStringNotContainsString('token_hash', (string) $r3->getContent());
        $this->assertStringNotContainsString('secret', (string) $r3->getContent());

        config()->set('chat.webhooks.endpoint_management_rate_limit.max_attempts', 1);
        config()->set('chat.webhooks.endpoint_management_rate_limit.decay_seconds', 60);

        $userOne = $this->actingAsWithPermissions(['chat.webhooks.view']);
        $this->getJson('/api/v1/chat/webhook-endpoints')->assertStatus(200);
        $this->getJson('/api/v1/chat/webhook-endpoints')->assertStatus(429);

        $userTwo = $this->actingAsWithPermissions(['chat.webhooks.view']);
        $this->assertNotSame($userOne->id, $userTwo->id);
        $this->getJson('/api/v1/chat/webhook-endpoints')->assertStatus(200);

        $externalLimiter = RateLimiter::limiter('chat-external-api');
        $this->assertIsCallable($externalLimiter);

        config()->set('chat.external_api.rate_limit.enabled', true);
        config()->set('chat.external_api.rate_limit.max_attempts', 7);
        config()->set('chat.external_api.rate_limit.decay_seconds', 13);
        $request = Request::create('/api/v1/chat/external/mock', 'GET');
        $request->setUserResolver(fn () => $userTwo);
        $limit = $externalLimiter($request);
        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(7, $limit->maxAttempts);
        $this->assertSame(13, $limit->decaySeconds);
    }
}

