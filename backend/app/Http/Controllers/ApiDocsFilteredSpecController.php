<?php

namespace App\Http\Controllers;

use App\Services\ApiDocsPermissionService;
use App\Services\ApiDocsOpenApiFilterService;
use App\Services\MetaCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;

class ApiDocsFilteredSpecController extends Controller
{
    /**
     * Return permission-filtered OpenAPI spec for current caller context.
     *
     * Uses versioned cache keys (RBAC + user bootstrap version) to avoid stale docs
     * after permission changes without global cache flushes.
     */
    public function __invoke(
        Request $request,
        Router $router,
        ApiDocsOpenApiFilterService $filterService,
        ApiDocsPermissionService $permissionService,
        MetaCacheService $metaCacheService
    ): JsonResponse {
        if (!$this->cacheEnabled()) {
            $filteredSpec = $this->buildFilteredSpec($request, $router, $filterService);
            return response()->json($filteredSpec);
        }

        $authUser = $request->user();
        $userId = is_object($authUser) && isset($authUser->id) ? (int) $authUser->id : 0;
        $rbacVersion = $metaCacheService->rbacVersion();
        $userVersion = $userId > 0 ? $metaCacheService->userBootstrapVersion($userId) : 1;
        $fullAccess = $permissionService->userHasFullDocsAccess($authUser) ? 1 : 0;
        // WHY:
        // Filtered OpenAPI output is permission-dependent.
        // Cache key must include RBAC/user versions to invalidate immediately after permission changes.
        $cacheKey = sprintf(
            'docs:openapi:filtered:user:%d:full:%d:rbac:%d:userv:%d',
            $userId,
            $fullAccess,
            $rbacVersion,
            $userVersion
        );

        $filteredSpec = $this->cacheStore()->remember(
            $cacheKey,
            now()->addSeconds($this->docsTtlSeconds()),
            fn (): array => $this->buildFilteredSpec($request, $router, $filterService)
        );

        return response()->json($filteredSpec);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFilteredSpec(
        Request $request,
        Router $router,
        ApiDocsOpenApiFilterService $filterService
    ): array {
        // WHY:
        // We reuse Scramble's raw spec generation once, then apply permission-aware filtering.
        // Internal attribute bypasses external raw-docs gate only for this trusted in-process dispatch.
        $baseSpecRequest = Request::create('/docs/api.json', 'GET');
        $baseSpecRequest->setUserResolver($request->getUserResolver());
        $baseSpecRequest->attributes->set('api_docs_internal_raw_access', true);

        $baseSpecResponse = $router->dispatch($baseSpecRequest);
        $decodedSpec = json_decode($baseSpecResponse->getContent(), true);
        $spec = is_array($decodedSpec) ? $decodedSpec : [];

        return $filterService->filterForUser($spec, $request->user());
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('performance.cache.enabled', true);
    }

    private function docsTtlSeconds(): int
    {
        return (int) config('performance.cache.api_docs_ttl', 600);
    }

    private function cacheStore(): \Illuminate\Contracts\Cache\Repository
    {
        $store = config('performance.cache.store');
        if (!is_string($store) || $store === '') {
            return Cache::store();
        }

        return Cache::store($store);
    }
}
