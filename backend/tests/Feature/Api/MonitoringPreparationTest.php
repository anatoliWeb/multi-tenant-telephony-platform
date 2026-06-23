<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoringPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_liveness_endpoint_returns_safe_status(): void
    {
        $response = $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString('REDIS_PASSWORD', $content);
        $this->assertStringNotContainsString('/var/www', $content);
    }

    public function test_protected_monitoring_health_endpoint_denies_guest(): void
    {
        $this->getJson('/api/v1/system/health')->assertUnauthorized();
    }

    public function test_protected_monitoring_health_endpoint_denies_user_without_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/system/health')->assertForbidden();
    }

    public function test_protected_monitoring_health_endpoint_allows_user_with_system_monitoring_permission(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'system.monitoring']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/system/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');

        $checks = $response->json('data.checks');
        $this->assertIsArray($checks);
        $this->assertArrayHasKey('database', $checks);
        $this->assertArrayHasKey('redis', $checks);
        $this->assertArrayHasKey('cache', $checks);
        $this->assertArrayHasKey('queue', $checks);
    }

    public function test_health_response_does_not_leak_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'system.monitoring']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);

        Sanctum::actingAs($user);

        $content = (string) $this->getJson('/api/v1/system/health')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString('REDIS_PASSWORD', $content);
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('token', strtolower($content));
        $this->assertStringNotContainsString('secret', strtolower($content));
        $this->assertStringNotContainsString('/var/www', $content);
    }

    public function test_monitoring_config_and_env_keys_exist(): void
    {
        $this->assertTrue(config()->has('monitoring.health.enabled'));
        $this->assertTrue(config()->has('monitoring.health.protected_enabled'));
        $this->assertTrue(config()->has('monitoring.health.checks.database'));
        $this->assertTrue(config()->has('monitoring.health.checks.redis'));
        $this->assertTrue(config()->has('monitoring.health.checks.cache'));
        $this->assertTrue(config()->has('monitoring.health.checks.queue'));

        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertIsString($envExample);
        $this->assertStringContainsString('MONITORING_HEALTH_ENABLED=', $envExample);
        $this->assertStringContainsString('MONITORING_HEALTH_PROTECTED_ENABLED=', $envExample);
        $this->assertStringContainsString('MONITORING_HEALTH_EXPOSE_DETAILS=', $envExample);
    }

    public function test_dependency_failure_returns_degraded_safe_response(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'system.monitoring']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Sanctum::actingAs($user);

        config()->set('queue.default', 'missing_connection');

        $response = $this->getJson('/api/v1/system/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'degraded');

        $this->assertSame('failed', $response->json('data.checks.queue'));
    }
}

