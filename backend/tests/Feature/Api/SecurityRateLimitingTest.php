<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_rate_limiting_policy_foundation(): void
    {
        $this->assertNotNull(config('security.rate_limits'));

        $loginRoute = app('router')->getRoutes()->getByName('api.v1.auth.login');
        $this->assertNotNull($loginRoute);
        $this->assertContains('throttle:auth-login', $loginRoute->gatherMiddleware());

        $docsUiRoute = app('router')->getRoutes()->getByName('scramble.docs.ui');
        $docsJsonRoute = app('router')->getRoutes()->getByName('scramble.docs.document');
        $this->assertNotNull($docsUiRoute);
        $this->assertNotNull($docsJsonRoute);
        $this->assertContains('throttle:api-docs', $docsUiRoute->gatherMiddleware());
        $this->assertContains('throttle:api-docs', $docsJsonRoute->gatherMiddleware());

        $portalRoute = app('router')->getRoutes()->getByName('docs.api.portal');
        $filteredRoute = app('router')->getRoutes()->getByName('docs.api.filtered');
        $this->assertNotNull($portalRoute);
        $this->assertNotNull($filteredRoute);
        $this->assertContains('throttle:api-docs', $portalRoute->gatherMiddleware());
        $this->assertContains('throttle:api-docs', $filteredRoute->gatherMiddleware());

        $typingStartRoute = app('router')->getRoutes()->getByName('api.v1.chat.conversations.typing.start');
        $typingStopRoute = app('router')->getRoutes()->getByName('api.v1.chat.conversations.typing.stop');
        $attachmentStoreRoute = app('router')->getRoutes()->getByName('api.v1.chat.attachments.store');
        $this->assertNotNull($typingStartRoute);
        $this->assertNotNull($typingStopRoute);
        $this->assertNotNull($attachmentStoreRoute);
        $this->assertContains('throttle:chat-typing', $typingStartRoute->gatherMiddleware());
        $this->assertContains('throttle:chat-typing', $typingStopRoute->gatherMiddleware());
        $this->assertContains('throttle:chat-attachments', $attachmentStoreRoute->gatherMiddleware());

        $sendRoute = app('router')->getRoutes()->getByName('api.v1.chat.messages.store');
        $externalRoute = app('router')->getRoutes()->getByName('api.v1.chat.external.messages.store');
        $webhookManagementRoute = app('router')->getRoutes()->getByName('api.v1.chat.webhook-endpoints.store');
        $this->assertNotNull($sendRoute);
        $this->assertNotNull($externalRoute);
        $this->assertNotNull($webhookManagementRoute);
        $this->assertContains('throttle:chat-message-send', $sendRoute->gatherMiddleware());
        $this->assertContains('throttle:chat-external-api', $externalRoute->gatherMiddleware());
        $this->assertContains('throttle:chat-webhook-management', $webhookManagementRoute->gatherMiddleware());
    }

    public function test_auth_login_endpoint_is_rate_limited_with_safe_429_response(): void
    {
        config()->set('security.rate_limits.enabled', true);
        config()->set('security.rate_limits.auth_login.max_attempts', 1);
        config()->set('security.rate_limits.auth_login.decay_seconds', 60);

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => 'limit@example.com',
            'password' => 'WrongPassword123!',
        ]);
        $this->assertNotSame(429, $first->status());

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => 'limit@example.com',
            'password' => 'WrongPassword123!',
        ])->assertStatus(429);

        $payload = (string) $second->getContent();
        $this->assertStringNotContainsString('WrongPassword123!', $payload);
        $this->assertStringNotContainsString('token', mb_strtolower($payload));
        $this->assertStringNotContainsString('secret', mb_strtolower($payload));
    }

    public function test_under_limit_requests_still_pass_for_docs_portal(): void
    {
        config()->set('security.rate_limits.enabled', true);
        config()->set('security.rate_limits.api_docs.max_attempts', 5);
        config()->set('security.rate_limits.api_docs.decay_seconds', 60);
        config()->set('api-docs.local_bypass', false);
        config()->set('app.env', 'production');

        $user = User::factory()->create();
        $permissionId = Permission::firstOrCreate(['name' => 'api.docs.view'])->id;
        $user->permissions()->sync([$permissionId]);
        Sanctum::actingAs($user);

        $this->get('/docs/api/portal')->assertOk();
        $this->get('/docs/api.filtered.json')->assertOk();
    }
}
