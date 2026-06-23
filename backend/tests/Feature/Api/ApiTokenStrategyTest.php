<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTokenStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_token_creation_with_scopes_uses_provided_abilities(): void
    {
        Permission::firstOrCreate(['name' => 'users.view']);
        Permission::firstOrCreate(['name' => 'roles.view']);

        $this->actingAsWithPermissions(['tokens.create']);

        $response = $this->postJson('/api/v1/tokens', [
            'name' => 'Scoped Token',
            'scopes' => ['users.view', 'roles.view'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.access_token.scopes', ['users.view', 'roles.view']);
    }

    public function test_token_creation_without_scopes_defaults_to_wildcard_ability(): void
    {
        $this->actingAsWithPermissions(['tokens.create']);

        $response = $this->postJson('/api/v1/tokens', [
            'name' => 'Wildcard Token',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.access_token.scopes', ['*']);
    }

    public function test_token_creation_rejects_invalid_scope_values(): void
    {
        $this->actingAsWithPermissions(['tokens.create']);

        $response = $this->postJson('/api/v1/tokens', [
            'name' => 'Invalid Scope Token',
            'scopes' => ['permissions.unknown'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scopes.0']);
    }

    public function test_token_list_only_shows_current_user_tokens(): void
    {
        $owner = $this->actingAsWithPermissions(['tokens.view']);
        $other = User::factory()->create();

        $ownerTokenA = $owner->createToken('Owner A')->accessToken->id;
        $ownerTokenB = $owner->createToken('Owner B')->accessToken->id;
        $other->createToken('Other Token');

        $response = $this->getJson('/api/v1/tokens');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $returnedIds = collect($response->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([$ownerTokenA, $ownerTokenB], $returnedIds);
    }

    public function test_user_can_delete_own_token(): void
    {
        $owner = $this->actingAsWithPermissions(['tokens.delete']);
        $tokenId = $owner->createToken('Delete Me')->accessToken->id;

        $response = $this->deleteJson("/api/v1/tokens/{$tokenId}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $owner = $this->actingAsWithPermissions(['tokens.delete']);
        $other = User::factory()->create();
        $otherTokenId = $other->createToken('Foreign Token')->accessToken->id;

        $response = $this->deleteJson("/api/v1/tokens/{$otherTokenId}");

        $response->assertForbidden();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherTokenId,
        ]);
    }

    public function test_deleted_token_cannot_access_protected_v1_auth_me_endpoint(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('Revoked Token');
        $plainToken = $newToken->plainTextToken;
        $tokenId = $newToken->accessToken->id;

        PersonalAccessToken::query()->findOrFail($tokenId)->delete();

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_valid_token_with_expiration_policy_can_access_protected_endpoint(): void
    {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->create();
        $plainToken = $user->createToken('Valid Expiring Token')->plainTextToken;

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_expired_token_cannot_access_protected_endpoint_when_expiration_policy_enabled(): void
    {
        config(['sanctum.expiration' => 1]);

        $user = User::factory()->create();
        $newToken = $user->createToken('Expired Token');
        $plainToken = $newToken->plainTextToken;

        $newToken->accessToken->forceFill([
            'created_at' => now()->subMinutes(2),
        ])->save();

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
