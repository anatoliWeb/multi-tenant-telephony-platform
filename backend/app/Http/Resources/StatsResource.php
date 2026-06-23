<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stats API resource.
 *
 * WHY THIS RESOURCE EXISTS:
 * Dashboard widgets require a predictable metrics schema independent from
 * internal service implementation details.
 *
 * WHY NOT RETURN RAW SERVICE/QUERY STRUCTURES:
 * Raw structures can drift over time and make frontend widgets fragile.
 *
 * WHAT THIS RESOURCE CONTROLS:
 * It defines exactly which metrics are exposed and keeps names stable for UI
 * cards and future versioning.
 */
class StatsResource extends JsonResource
{
    /**
     * Transform stats payload into stable API structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stats = data_get($this->resource, 'data', []);
        $recentActivity = collect(data_get($stats, 'recent_activity', []))
            ->map(fn ($item) => $this->transformActivityItem($item))
            ->values()
            ->all();

        return [
            'users' => data_get($stats, 'users', 0),
            'roles' => data_get($stats, 'roles', 0),
            'permissions' => data_get($stats, 'permissions', 0),
            'activity_logs' => data_get($stats, 'activity_logs', 0),
            'admins' => data_get($stats, 'admins', 0),
            'managers' => data_get($stats, 'managers', 0),
            'tokens' => data_get($stats, 'tokens', 0),
            'users_with_direct_permissions' => data_get($stats, 'users_with_direct_permissions', 0),
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Add translated labels for activity module/action/status while preserving
     * existing machine-readable fields used by clients.
     *
     * @param mixed $item
     * @return array<string, mixed>
     */
    protected function transformActivityItem(mixed $item): array
    {
        $row = is_array($item) ? $item : (array) $item;

        $action = (string) data_get($row, 'action', '');
        $status = (string) data_get($row, 'meta.status', 'success');
        $module = $this->detectModuleFromAction($action);
        $actionName = $this->detectActionName($action);

        $row['module_label'] = $this->translateWithFallback('activity.module.' . $module, $module);
        $row['action_label'] = $this->translateWithFallback('activity.actions.' . $actionName, $actionName);
        $row['status_label'] = $this->translateWithFallback('activity.status.' . $status, $status);

        return $row;
    }

    protected function detectModuleFromAction(string $action): string
    {
        if ($action === '') {
            return 'system';
        }

        if (str_contains($action, '.')) {
            return explode('.', $action, 2)[0] ?: 'system';
        }

        if (str_contains($action, '_')) {
            return explode('_', $action, 2)[0] ?: 'system';
        }

        return 'system';
    }

    protected function detectActionName(string $action): string
    {
        if ($action === '') {
            return 'updated';
        }

        if (str_contains($action, '.')) {
            return explode('.', $action, 2)[1] ?? 'updated';
        }

        if (str_contains($action, '_')) {
            $parts = explode('_', $action);
            return end($parts) ?: 'updated';
        }

        return $action;
    }

    protected function translateWithFallback(string $key, string $fallback): string
    {
        $translated = dt($key);
        return $translated === $key ? $fallback : $translated;
    }
}
