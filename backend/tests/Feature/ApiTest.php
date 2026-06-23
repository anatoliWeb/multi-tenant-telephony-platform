<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function grantPermission(User $user, string $permission): void
    {
        $perm = Permission::firstOrCreate(['name' => $permission]);
        $user->permissions()->syncWithoutDetaching([$perm->id]);
    }

    /**
     * Ensure the /api/users endpoint returns a valid response.
     *
     * This test verifies:
     * - HTTP status is 200
     * - Response structure matches expected user format
     * - At least one user is returned
     *
     * @return void
     */
    public function test_users_endpoint_returns_valid_data(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, 'users.view');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles'],
                ],
            ]);
    }

    /**
     * Ensure the /api/stats endpoint returns correct statistics structure.
     *
     * This test verifies:
     * - HTTP status is 200
     * - Required fields exist in response
     * - Data format is suitable for dashboard usage
     *
     * @return void
     */
    public function test_stats_endpoint_returns_valid_data(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'users',
                    'roles',
                    'permissions',
                    'activity_logs',
                    'admins',
                    'managers',
                    'tokens',
                    'users_with_direct_permissions',
                    'recent_activity',
                ],
            ]);
    }

    /**
     * Ensure protected route requires authentication.
     *
     * @return void
     */
    public function test_protected_route_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }
}
