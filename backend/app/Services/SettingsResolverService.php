<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Hierarchical settings resolver.
 *
 * WHY THIS SERVICE EXISTS:
 * Enterprise settings require deterministic inheritance rules. Resolving this
 * in one service avoids duplicated precedence logic and guarantees predictable
 * output for API, jobs, and future realtime consumers.
 */
class SettingsResolverService
{
    protected const CACHE_PREFIX = 'settings:resolved';

    protected const SCOPE_WEIGHT_USER = 400;
    protected const SCOPE_WEIGHT_PERMISSION = 300;
    protected const SCOPE_WEIGHT_ROLE = 200;
    protected const SCOPE_WEIGHT_GLOBAL = 100;

    /**
     * Resolve one setting for current app context (global only).
     */
    public function get(string $key, ?string $channel = null): array
    {
        return $this->resolveFromCandidates(
            $this->baseQuery($channel)->where('key', $key)->get()
        );
    }

    /**
     * Resolve one setting for a specific user with full scope inheritance.
     */
    public function getForUser(User $user, string $key, ?string $channel = null): array
    {
        $cacheKey = $this->userCacheKey($user->id, $key, $channel);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $key, $channel): array {
            $candidates = $this->queryForUser($user, $channel)
                ->where('key', $key)
                ->get();

            return $this->resolveFromCandidates($candidates);
        });
    }

    /**
     * Resolve all settings for a user and return keyed map.
     *
     * @return array<string, array<string, mixed>>
     */
    public function resolveAllForUser(User $user, ?string $channel = null): array
    {
        $cacheKey = $this->userCacheKey($user->id, '*', $channel);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $channel): array {
            $scopeContext = $this->buildUserScopeContext($user);
            $candidates = $this->queryForUser($user, $channel, $scopeContext)
                ->orderBy('key')
                ->get()
                ->groupBy('key');

            $resolved = [];
            foreach ($candidates as $key => $items) {
                $resolved[$key] = $this->resolveFromCandidates($items);
            }

            return $resolved;
        });
    }

    /**
     * Resolve only requested keys for a specific user.
     *
     * WHY:
     * Settings table views often show filtered subsets. Resolving the complete
     * inheritance tree for every request causes avoidable latency spikes.
     *
     * @param array<int, string> $keys
     * @return array<string, array<string, mixed>>
     */
    public function resolveManyForUser(User $user, array $keys, ?string $channel = null): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn ($key) => is_string($key) && $key !== '')));
        if ($keys === []) {
            return [];
        }

        $scopeContext = $this->buildUserScopeContext($user);
        $candidates = $this->queryForUser($user, $channel, $scopeContext)
            ->whereIn('key', $keys)
            ->orderBy('key')
            ->get()
            ->groupBy('key');

        $resolved = [];
        foreach ($keys as $key) {
            $resolved[$key] = $this->resolveFromCandidates($candidates->get($key, collect()));
        }

        return $resolved;
    }

    /**
     * Typed value parsing by setting type.
     */
    public function castValue(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $raw,
            'float', 'number' => (float) $raw,
            'boolean', 'toggle' => filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'json', 'array' => json_decode($raw, true) ?? [],
            default => $raw,
        };
    }

    /**
     * Invalidate resolver caches after mutations.
     */
    public function invalidateCaches(?int $userId = null): void
    {
        if ($userId !== null) {
            Cache::forget($this->userCacheKey($userId, '*', null));
            Cache::forget($this->userCacheKey($userId, '*', 'frontend'));
            Cache::forget($this->userCacheKey($userId, '*', 'backend'));
            return;
        }

        // Lightweight fallback for file cache stores that do not support tags.
        Cache::flush();
    }

    protected function queryForUser(User $user, ?string $channel = null, ?array $scopeContext = null)
    {
        $scopeContext ??= $this->buildUserScopeContext($user);
        $roleIds = $scopeContext['role_ids'];
        $permissionIds = $scopeContext['permission_ids'];

        return $this->baseQuery($channel)
            ->where(function ($query) use ($user, $roleIds, $permissionIds): void {
                $query->whereNull('scope_user_id')
                    ->whereNull('scope_role_id')
                    ->whereNull('scope_permission_id')
                    ->orWhere('scope_user_id', $user->id)
                    ->orWhereIn('scope_role_id', $roleIds)
                    ->orWhereIn('scope_permission_id', $permissionIds);
            });
    }

    protected function baseQuery(?string $channel = null)
    {
        return SystemSetting::query()
            ->where('is_active', true)
            ->when($channel === 'frontend', fn ($query) => $query->where('is_frontend', true))
            ->when($channel === 'backend', fn ($query) => $query->where('is_backend', true));
    }

    /**
     * Deterministic precedence strategy:
     * user > permission > role > global, then priority desc, then newest ID.
     */
    protected function resolveFromCandidates(Collection $candidates): array
    {
        if ($candidates->isEmpty()) {
            return [
                'value' => null,
                'source' => 'missing',
                'raw_value' => null,
                'type' => 'string',
                'setting_id' => null,
                'priority' => null,
            ];
        }

        /** @var SystemSetting $winner */
        $winner = $candidates
            ->sortByDesc(fn (SystemSetting $setting) => [
                $this->scopeWeight($setting),
                (int) $setting->priority,
                (int) $setting->id,
            ])
            ->first();

        $raw = $winner->value ?? $winner->default_value;

        return [
            'value' => $this->castValue($raw, $winner->type),
            'raw_value' => $raw,
            'type' => $winner->type,
            'source' => $this->scopeName($winner),
            'setting_id' => $winner->id,
            'priority' => $winner->priority,
        ];
    }

    protected function scopeWeight(SystemSetting $setting): int
    {
        if ($setting->scope_user_id !== null) {
            return self::SCOPE_WEIGHT_USER;
        }
        if ($setting->scope_permission_id !== null) {
            return self::SCOPE_WEIGHT_PERMISSION;
        }
        if ($setting->scope_role_id !== null) {
            return self::SCOPE_WEIGHT_ROLE;
        }

        return self::SCOPE_WEIGHT_GLOBAL;
    }

    protected function scopeName(SystemSetting $setting): string
    {
        if ($setting->scope_user_id !== null) {
            return 'user';
        }
        if ($setting->scope_permission_id !== null) {
            return 'permission';
        }
        if ($setting->scope_role_id !== null) {
            return 'role';
        }

        return 'global';
    }

    /**
     * Merge direct + role permissions and remove explicit denied permissions.
     *
     * @return array<int, string>
     */
    protected function collectEffectivePermissionNames(User $user): array
    {
        $direct = $user->permissions()->pluck('permissions.name')->all();

        $viaRoles = Permission::query()
            ->whereHas('roles.users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('name')
            ->all();

        $denied = $user->deniedPermissions()->pluck('permissions.name')->all();

        return array_values(array_diff(array_unique([...$direct, ...$viaRoles]), $denied));
    }

    /**
     * @return array{role_ids: array<int, int>, permission_ids: array<int, int>}
     */
    protected function buildUserScopeContext(User $user): array
    {
        $roleIds = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
        $permissionIds = Permission::query()
            ->whereIn('name', $this->collectEffectivePermissionNames($user))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'role_ids' => $roleIds,
            'permission_ids' => $permissionIds,
        ];
    }

    protected function userCacheKey(int $userId, string $key, ?string $channel): string
    {
        $channelName = $channel ?: 'all';
        return sprintf('%s:user:%d:key:%s:channel:%s', self::CACHE_PREFIX, $userId, $key, $channelName);
    }
}
