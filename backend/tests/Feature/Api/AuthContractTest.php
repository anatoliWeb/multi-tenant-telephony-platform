<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_auth_contract_guards_login_me_and_logout_flow(): void
    {
        $this->getJson('/api/v1/auth/session/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');

        $this->postJson('/api/v1/auth/session/logout')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');

        User::factory()->create([
            'email' => 'session-contract@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $login = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'session-contract@example.com',
            'password' => 'secret123',
            'remember' => false,
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'permissions',
                    'roles',
                ],
            ]);

        $this->getJson('/api/v1/auth/session/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'session-contract@example.com');

        $this->postJson('/api/v1/auth/session/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGuest('web');
    }

    public function test_session_login_invalid_credentials_returns_safe_validation_error(): void
    {
        User::factory()->create([
            'email' => 'session-invalid@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'session-invalid@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['email']]);

        $this->assertAuthErrorResponseIsSafe($response->getContent());
    }

    public function test_bearer_login_me_logout_and_revoked_token_contract(): void
    {
        User::factory()->create([
            'email' => 'bearer-contract@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'bearer-contract@example.com',
            'password' => 'secret123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email'],
                    'permissions',
                    'roles',
                ],
            ]);

        $plainToken = (string) $login->json('data.token');
        $this->assertNotSame('', $plainToken);
        $this->assertArrayNotHasKey('token_hash', (array) $login->json('data'));

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'bearer-contract@example.com');

        $this->withToken($plainToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Reset the in-memory guard so the revoked-token check mirrors a fresh request lifecycle.
        $this->refreshApplication();

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_bearer_auth_validation_and_invalid_token_errors_are_standardized_and_safe(): void
    {
        $validation = $this->postJson('/api/v1/auth/token', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertAuthErrorResponseIsSafe($validation->getContent());

        User::factory()->create([
            'email' => 'token-invalid@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $invalidCredentials = $this->postJson('/api/v1/auth/token', [
            'email' => 'token-invalid@example.com',
            'password' => 'wrong-password',
        ]);

        $invalidCredentials->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials');

        $this->assertAuthErrorResponseIsSafe($invalidCredentials->getContent());

        $invalidToken = $this->withToken('invalid-token-value')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');

        $this->assertAuthErrorResponseIsSafe($invalidToken->getContent());
    }

    public function test_token_and_session_me_return_consistent_auth_contract_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'contract@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $role = Role::create(['name' => 'contract-role']);

        $rolePermission = Permission::firstOrCreate(['name' => 'reports.view']);
        $directPermission = Permission::firstOrCreate(['name' => 'settings.view']);
        $deniedPermission = Permission::firstOrCreate(['name' => 'users.delete']);

        $role->permissions()->sync([$rolePermission->id, $deniedPermission->id]);
        $user->roles()->sync([$role->id]);
        $user->permissions()->sync([$directPermission->id]);
        $user->deniedPermissions()->sync([$deniedPermission->id]);

        $tokenLogin = $this->postJson('/api/v1/auth/token', [
            'email' => 'contract@example.com',
            'password' => 'secret123',
        ]);

        $tokenLogin->assertOk()
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

        $plainToken = $tokenLogin->json('data.token');
        $this->assertIsString($plainToken);
        $this->assertNotEmpty($plainToken);

        $tokenMe = $this
            ->withToken((string) $plainToken)
            ->getJson('/api/v1/auth/me');

        $tokenMe->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'permissions',
                    'roles',
                ],
            ]);

        $sessionLogin = $this->postJson('/api/v1/auth/session/login', [
            'email' => 'contract@example.com',
            'password' => 'secret123',
            'remember' => true,
        ]);

        $sessionLogin->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'permissions',
                    'roles',
                ],
            ]);

        $sessionMe = $this->getJson('/api/v1/auth/session/me');

        $sessionMe->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'permissions',
                    'roles',
                ],
            ]);

        $tokenPayload = $tokenMe->json('data');
        $sessionPayload = $sessionMe->json('data');

        $this->assertSame($tokenPayload['user']['id'], $sessionPayload['user']['id']);
        $this->assertSame($tokenPayload['user']['email'], $sessionPayload['user']['email']);

        $tokenRoles = $tokenPayload['roles'];
        $sessionRoles = $sessionPayload['roles'];
        sort($tokenRoles);
        sort($sessionRoles);
        $this->assertSame($tokenRoles, $sessionRoles);
        $this->assertContains('contract-role', $tokenRoles);

        $tokenPermissions = $tokenPayload['permissions'];
        $sessionPermissions = $sessionPayload['permissions'];
        sort($tokenPermissions);
        sort($sessionPermissions);
        $this->assertSame($tokenPermissions, $sessionPermissions);

        $this->assertContains('reports.view', $tokenPermissions);
        $this->assertContains('settings.view', $tokenPermissions);
        $this->assertNotContains('users.delete', $tokenPermissions);
    }

    public function test_auth_payload_permissions_refresh_after_user_rbac_update(): void
    {
        $user = User::factory()->create([
            'email' => 'cache-refresh@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $role = Role::create(['name' => 'cache-role']);
        $rolePermission = Permission::firstOrCreate(['name' => 'reports.view']);
        $directPermission = Permission::firstOrCreate(['name' => 'settings.view']);
        $deniedPermission = Permission::firstOrCreate(['name' => 'users.delete']);
        $newDirectPermission = Permission::firstOrCreate(['name' => 'tokens.view']);

        $role->permissions()->sync([$rolePermission->id, $deniedPermission->id]);
        $user->roles()->sync([$role->id]);
        $user->permissions()->sync([$directPermission->id]);
        $user->deniedPermissions()->sync([$deniedPermission->id]);

        $tokenLogin = $this->postJson('/api/v1/auth/token', [
            'email' => 'cache-refresh@example.com',
            'password' => 'secret123',
        ])->assertOk();

        $plainToken = (string) $tokenLogin->json('data.token');

        $initialMe = $this->withToken($plainToken)->getJson('/api/v1/auth/me');
        $initialMe->assertOk();

        $initialPermissions = $initialMe->json('data.permissions');
        $this->assertContains('reports.view', $initialPermissions);
        $this->assertContains('settings.view', $initialPermissions);
        $this->assertNotContains('users.delete', $initialPermissions);

        $operator = User::factory()->create();
        $this->actingAs($operator, 'web');

        /** @var UserService $userService */
        $userService = app(UserService::class);
        $userService->update($user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [$role->id],
            'permissions' => ['tokens.view'],
            'denied_permissions' => [],
        ]);
        Auth::guard('web')->logout();

        $updatedMe = $this->withToken($plainToken)->getJson('/api/v1/auth/me');
        $updatedMe->assertOk();

        $updatedPermissions = $updatedMe->json('data.permissions');
        $this->assertContains('tokens.view', $updatedPermissions);
        $this->assertNotContains('settings.view', $updatedPermissions);
    }

    public function test_auth_payload_permissions_refresh_after_role_permissions_update(): void
    {
        $user = User::factory()->create([
            'email' => 'role-cache-refresh@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $role = Role::create(['name' => 'role-cache-role']);
        $initialPermission = Permission::firstOrCreate(['name' => 'reports.view']);
        $nextPermission = Permission::firstOrCreate(['name' => 'tokens.view']);
        $role->permissions()->sync([$initialPermission->id]);
        $user->roles()->sync([$role->id]);

        $tokenLogin = $this->postJson('/api/v1/auth/token', [
            'email' => 'role-cache-refresh@example.com',
            'password' => 'secret123',
        ])->assertOk();

        $plainToken = (string) $tokenLogin->json('data.token');

        $initialMe = $this->withToken($plainToken)->getJson('/api/v1/auth/me');
        $initialMe->assertOk();
        $initialPermissions = $initialMe->json('data.permissions');
        $this->assertContains('reports.view', $initialPermissions);
        $this->assertNotContains('tokens.view', $initialPermissions);

        $operator = User::factory()->create();
        $this->actingAs($operator, 'web');

        /** @var RoleService $roleService */
        $roleService = app(RoleService::class);
        $roleService->update($role, [
            'permissions' => ['tokens.view'],
        ]);
        Auth::guard('web')->logout();

        $updatedMe = $this->withToken($plainToken)->getJson('/api/v1/auth/me');
        $updatedMe->assertOk();
        $updatedPermissions = $updatedMe->json('data.permissions');
        $this->assertContains('tokens.view', $updatedPermissions);
        $this->assertNotContains('reports.view', $updatedPermissions);
    }

    private function assertAuthErrorResponseIsSafe(string $content): void
    {
        $lower = mb_strtolower($content);

        foreach ([
            'secret123',
            'wrong-password',
            'token_hash',
            'access_token',
            'authorization',
            'stack trace',
            'trace',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $lower);
        }
    }
}
