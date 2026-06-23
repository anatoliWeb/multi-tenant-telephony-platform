<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V1AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Assert standard auth context payload structure.
     */
    protected function assertAuthContextShape($response): void
    {
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'permissions',
                    'roles',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_token_login_success_returns_shared_auth_contract(): void
    {
        User::factory()->create([
            'email' => 'v1auth@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'v1auth@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user',
                    'permissions',
                    'roles',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_token_login_invalid_credentials_returns_unauthorized(): void
    {
        User::factory()->create([
            'email' => 'v1auth@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'v1auth@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_token_me_returns_user_permissions_and_roles(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'permissions',
                    'roles',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_token_logout_revokes_current_access_token(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('logout-token');
        $plainTextToken = $newToken->plainTextToken;
        $tokenId = $newToken->accessToken->id;

        $response = $this
            ->withToken($plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    public function test_session_login_success_returns_shared_auth_contract(): void
    {
        User::factory()->create([
            'email' => 'session@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'session@example.com',
            'password' => 'secret123',
            'remember' => true,
        ]);

        $this->assertAuthContextShape($response);
    }

    public function test_session_me_returns_user_permissions_and_roles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/v1/auth/session/me');

        $this->assertAuthContextShape($response);
    }

    public function test_session_logout_clears_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/v1/auth/session/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGuest('web');
    }

    public function test_session_login_with_remember_true_persists_context_for_followup_session_me(): void
    {
        User::factory()->create([
            'email' => 'remember.true@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'remember.true@example.com',
            'password' => 'secret123',
            'remember' => true,
        ]);

        $this->assertAuthContextShape($loginResponse);
        $this->assertAuthenticated('web');

        $sessionMeResponse = $this->getJson('/api/v1/auth/session/me');
        $this->assertAuthContextShape($sessionMeResponse);

        $this->assertSame(
            $loginResponse->json('data.user.id'),
            $sessionMeResponse->json('data.user.id'),
        );

        // Recaller cookie should be issued when remember is enabled.
        $this->assertNotNull($loginResponse->headers->getCookies()[0] ?? null);
        $this->assertTrue(
            str_contains(
                implode(';', array_map(
                    fn ($cookie) => $cookie->getName(),
                    $loginResponse->headers->getCookies()
                )),
                'remember_web_'
            )
        );
    }

    public function test_session_login_with_remember_false_keeps_auth_context_for_followup_session_me(): void
    {
        User::factory()->create([
            'email' => 'remember.false@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'remember.false@example.com',
            'password' => 'secret123',
            'remember' => false,
        ]);

        $this->assertAuthContextShape($loginResponse);
        $this->assertAuthenticated('web');

        $sessionMeResponse = $this->getJson('/api/v1/auth/session/me');
        $this->assertAuthContextShape($sessionMeResponse);

        $this->assertSame(
            $loginResponse->json('data.user.id'),
            $sessionMeResponse->json('data.user.id'),
        );

        // Recaller cookie should not be issued when remember is disabled.
        $this->assertFalse(
            str_contains(
                implode(';', array_map(
                    fn ($cookie) => $cookie->getName(),
                    $loginResponse->headers->getCookies()
                )),
                'remember_web_'
            )
        );
    }
}
