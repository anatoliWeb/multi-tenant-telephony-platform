<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApiDocsOpenApiFilterService
{
    public function __construct(
        private readonly ApiDocsPermissionService $permissionService
    ) {
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function filterForUser(array $spec, ?User $user): array
    {
        $paths = Arr::get($spec, 'paths', []);
        if (! is_array($paths)) {
            $paths = [];
        }

        $spec['paths'] = $this->filterPaths($paths, $user);
        $spec = $this->stripForbiddenSensitiveFields($spec);

        return $spec;
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return array<string, mixed>
     */
    public function filterPaths(array $paths, ?User $user): array
    {
        $filtered = [];

        foreach ($paths as $path => $definition) {
            if (! is_string($path)) {
                continue;
            }

            if ($this->shouldKeepPath($path, $user)) {
                $filtered[$path] = $definition;
            }
        }

        return $filtered;
    }

    /**
     * Decide whether a path should remain in user-scoped OpenAPI output.
     *
     * Internal/hidden paths are always removed. Remaining paths are filtered by
     * permission-aware group rules unless caller has full docs access.
     */
    public function shouldKeepPath(string $path, ?User $user): bool
    {
        if ($this->isInternalOrHiddenPath($path)) {
            return false;
        }

        if ($this->permissionService->userHasFullDocsAccess($user)) {
            return true;
        }

        return $this->permissionService->userCanSeePath($user, $this->normalizeToApiV1Path($path));
    }

    private function normalizeToApiV1Path(string $path): string
    {
        $normalized = '/'.ltrim($path, '/');

        if (Str::startsWith($normalized, '/api/v1/')) {
            return $normalized;
        }

        if ($normalized === '/api/v1') {
            return $normalized;
        }

        return '/api/v1'.$normalized;
    }

    private function isInternalOrHiddenPath(string $path): bool
    {
        $normalized = '/'.ltrim($path, '/');

        foreach ([
            '/broadcasting/auth',
            '/admin*',
            '/telescope*',
            '/horizon*',
            '/docs*',
        ] as $pattern) {
            if (Str::is($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function stripForbiddenSensitiveFields(array $spec): array
    {
        $forbiddenFieldNames = [
            'token_hash',
            'webhook_secret',
            'device_key',
            'raw_payload',
            'raw_response',
        ];

        /** @var array<string, mixed> $cleaned */
        $cleaned = $this->stripForbiddenRecursive($spec, $forbiddenFieldNames);

        return $cleaned;
    }

    /**
     * @param  mixed  $value
     * @param  array<int, string>  $forbiddenFieldNames
     * @return mixed
     */
    private function stripForbiddenRecursive(mixed $value, array $forbiddenFieldNames): mixed
    {
        // "required" arrays must stay aligned with removed properties to avoid invalid schema references.
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $forbiddenFieldNames, true)) {
                continue;
            }

            if (is_array($item)) {
                $filteredItem = $this->stripForbiddenRecursive($item, $forbiddenFieldNames);

                if (is_string($key) && $key === 'required' && array_is_list($filteredItem)) {
                    $filteredItem = array_values(array_filter(
                        $filteredItem,
                        fn (mixed $requiredKey): bool => ! (is_string($requiredKey) && in_array($requiredKey, $forbiddenFieldNames, true))
                    ));
                }

                $result[$key] = $filteredItem;
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
