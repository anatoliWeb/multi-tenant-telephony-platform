<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use App\Services\MetaCacheService;
use App\Services\Rbac\PermissionCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedisCachingFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();

        config()->set('api-docs.local_bypass', false);
        config()->set('performance.cache.enabled', true);
        config()->set('performance.cache.store', 'array');
    }

    public function test_performance_cache_config_keys_exist(): void
    {
        $this->assertNotNull(config('performance.cache.enabled'));
        $this->assertNotNull(config('performance.cache.default_ttl'));
        $this->assertNotNull(config('performance.cache.meta_ttl'));
        $this->assertNotNull(config('performance.cache.rbac_ttl'));
        $this->assertNotNull(config('performance.cache.api_docs_ttl'));
        $this->assertNotNull(config('performance.cache.stats_ttl'));
    }

    public function test_permission_cache_invalidation_is_version_based_not_global_flush(): void
    {
        /** @var PermissionCacheService $service */
        $service = app(PermissionCacheService::class);
        $user = User::factory()->create();

        $globalBefore = $service->globalVersion();
        $userBefore = $service->userVersion((int) $user->id);

        $service->forgetForUserId((int) $user->id);
        $this->assertGreaterThan($userBefore, $service->userVersion((int) $user->id));
        $this->assertSame($globalBefore, $service->globalVersion());

        $service->forgetAll();
        $this->assertGreaterThan($globalBefore, $service->globalVersion());
    }

    public function test_filtered_openapi_cache_is_user_scoped_and_safe(): void
    {
        $this->grantPermissions($userA = User::factory()->create(), ['api.docs.view', 'users.view']);
        $this->grantPermissions($userB = User::factory()->create(), ['api.docs.view', 'chat.conversations.view']);

        $specA = $this->actingAs($userA)->getJson('/docs/api.filtered.json')->assertOk()->json();
        $specB = $this->actingAs($userB)->getJson('/docs/api.filtered.json')->assertOk()->json();

        $pathsA = implode(' ', array_keys((array) data_get($specA, 'paths', [])));
        $pathsB = implode(' ', array_keys((array) data_get($specB, 'paths', [])));

        $this->assertStringContainsString('/users', $pathsA);
        $this->assertStringNotContainsString('/chat/conversations', $pathsA);
        $this->assertStringContainsString('/chat/conversations', $pathsB);
        $this->assertStringNotContainsString('/users', $pathsB);

        /** @var MetaCacheService $metaCache */
        $metaCache = app(MetaCacheService::class);
        $rbacVersion = $metaCache->rbacVersion();
        $userAVersion = $metaCache->userBootstrapVersion((int) $userA->id);
        $userBVersion = $metaCache->userBootstrapVersion((int) $userB->id);

        $keyA = sprintf(
            'docs:openapi:filtered:user:%d:full:%d:rbac:%d:userv:%d',
            $userA->id,
            0,
            $rbacVersion,
            $userAVersion
        );
        $keyB = sprintf(
            'docs:openapi:filtered:user:%d:full:%d:rbac:%d:userv:%d',
            $userB->id,
            0,
            $rbacVersion,
            $userBVersion
        );

        $this->assertTrue(Cache::store('array')->has($keyA));
        $this->assertTrue(Cache::store('array')->has($keyB));

        $cachedA = json_encode(Cache::store('array')->get($keyA), JSON_THROW_ON_ERROR);
        $cachedB = json_encode(Cache::store('array')->get($keyB), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('token_hash', $cachedA);
        $this->assertStringNotContainsString('webhook_secret', $cachedA);
        $this->assertStringNotContainsString('"authorization"', strtolower($cachedA));
        $this->assertStringNotContainsString('device_key', $cachedB);
        $this->assertStringNotContainsString('raw_payload', $cachedB);
    }

    public function test_cache_can_be_disabled_for_runtime_bypass(): void
    {
        config()->set('performance.cache.enabled', false);
        $this->grantPermissions($user = User::factory()->create(), ['api.docs.view']);

        $this->actingAs($user)->getJson('/docs/api.filtered.json')->assertOk();

        /** @var MetaCacheService $metaCache */
        $metaCache = app(MetaCacheService::class);
        $cacheKey = sprintf(
            'docs:openapi:filtered:user:%d:full:%d:rbac:%d:userv:%d',
            $user->id,
            0,
            $metaCache->rbacVersion(),
            $metaCache->userBootstrapVersion((int) $user->id)
        );

        $this->assertFalse(Cache::store('array')->has($cacheKey));
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grantPermissions(User $user, array $permissions): void
    {
        $ids = collect($permissions)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name])->id)
            ->all();

        $user->permissions()->syncWithoutDetaching($ids);
    }
}
