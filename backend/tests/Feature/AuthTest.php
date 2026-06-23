<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can receive API token with valid credentials.
     *
     * This test verifies:
     * - user exists in database
     * - correct credentials return 200
     * - token is returned in response
     *
     * @return void
     */
    public function test_user_can_get_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/token', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token'
            ]);
    }

    /**
     * Test that invalid credentials return unauthorized response.
     *
     * This test verifies:
     * - wrong password returns 401
     * - no token is issued
     *
     * @return void
     */
    public function test_invalid_credentials_return_unauthorized(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/token', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials'
            ]);
    }

    /**
     * Test that validation errors are returned for invalid input.
     *
     * This test verifies:
     * - missing fields trigger validation errors
     * - proper HTTP status is returned
     *
     * @return void
     */
    public function test_validation_errors_are_returned(): void
    {
        $response = $this->postJson('/api/token', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}