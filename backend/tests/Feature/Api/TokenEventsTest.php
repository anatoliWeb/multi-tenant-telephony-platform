<?php

namespace Tests\Feature\Api;

use App\Events\Auth\TokenCreated;
use App\Events\Auth\TokenRevoked;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenEventsTest extends TestCase
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

    public function test_token_create_dispatches_token_created_event(): void
    {
        Permission::firstOrCreate(['name' => 'users.view']);
        $this->actingAsWithPermissions(['tokens.create']);

        Event::fakeFor(function (): void {
            $response = $this->postJson('/api/v1/tokens', [
                'name' => 'Domain Token',
                'scopes' => ['users.view'],
            ]);

            $response->assertCreated();

            Event::assertDispatched(TokenCreated::class, function (TokenCreated $event): bool {
                return $event->tokenName === 'Domain Token'
                    && in_array('users.view', $event->abilities, true);
            });
        });
    }

    public function test_token_delete_dispatches_token_revoked_event(): void
    {
        $owner = $this->actingAsWithPermissions(['tokens.delete']);
        $tokenId = $owner->createToken('Delete With Event')->accessToken->id;

        Event::fakeFor(function () use ($tokenId): void {
            $response = $this->deleteJson("/api/v1/tokens/{$tokenId}");
            $response->assertOk();

            Event::assertDispatched(TokenRevoked::class, function (TokenRevoked $event) use ($tokenId): bool {
                return $event->tokenId === $tokenId
                    && $event->revokeReason === 'user_requested';
            });
        });
    }

    public function test_token_logout_dispatches_token_revoked_event(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('Logout Event Token');
        $plainToken = $newToken->plainTextToken;
        $tokenId = $newToken->accessToken->id;

        Event::fakeFor(function () use ($plainToken, $tokenId): void {
            $response = $this->withToken($plainToken)->postJson('/api/v1/auth/logout');
            $response->assertOk();

            Event::assertDispatched(TokenRevoked::class, function (TokenRevoked $event) use ($tokenId): bool {
                return $event->tokenId === $tokenId
                    && $event->revokeReason === 'logout';
            });
        });
    }
}
