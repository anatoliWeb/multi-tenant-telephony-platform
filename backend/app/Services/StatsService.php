<?php

namespace App\Services;

use App\DTO\StatsDTO;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use App\Services\ActivityService;

class StatsService
{
    protected ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Get cached dashboard statistics payload.
     *
     * Contract shape is wrapped in a stable API envelope ("data" key) because multiple
     * dashboard clients consume the same response contract.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        if (!$this->cacheEnabled()) {
            return $this->buildStatsPayload();
        }

        $cacheKey = 'stats:dashboard:summary:v1';

        /** @var array<string, mixed> $payload */
        $payload = $this->cacheStore()->remember(
            $cacheKey,
            now()->addSeconds($this->statsTtlSeconds()),
            fn (): array => $this->buildStatsPayload()
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStatsPayload(): array
    {
        $stats = new StatsDTO(
            users: User::count(),
            roles: Role::count(),
            permissions: Permission::count(),
            activityLogs: ActivityLog::count(),
            admins: User::whereHas('roles', fn($q) =>
                $q->where('name', 'admin')
            )->count(),
            managers: User::whereHas('roles', fn($q) =>
                $q->where('name', 'manager')
            )->count(),
            tokens: PersonalAccessToken::count(),
            usersWithDirectPermissions: User::whereHas('permissions')->count(),
            recentActivity: $this->normalizeRecentActivity(
                $this->activityService->getRecent()
            ),
        );

        return [
            'data' => $stats->toArray(),
        ];
    }

    /**
     * Normalize recent activity payload to plain array for DTO typing.
     *
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRecentActivity(mixed $value): array
    {
        if ($value instanceof Collection) {
            /** @var array<int, array<string, mixed>> $normalized */
            $normalized = $value->values()->all();
            return $normalized;
        }

        if (is_array($value)) {
            return array_values($value);
        }

        return [];
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('performance.cache.enabled', true);
    }

    protected function statsTtlSeconds(): int
    {
        return (int) config('performance.cache.stats_ttl', 60);
    }

    protected function cacheStore(): CacheRepository
    {
        $store = config('performance.cache.store');
        if (!is_string($store) || $store === '') {
            return Cache::store();
        }

        return Cache::store($store);
    }


}
