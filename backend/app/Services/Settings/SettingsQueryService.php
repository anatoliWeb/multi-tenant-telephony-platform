<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SettingsResolverService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SettingsQueryService
{
    public function __construct(
        protected SettingsResolverService $resolver
    ) {
    }

    /**
     * Build settings list payload for admin/API screens.
     *
     * WHY:
     * Settings listing has filtering, pagination and effective-value resolution.
     * This workflow is business/query orchestration and should not live in controller.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     paginator: LengthAwarePaginator,
     *     settings: Collection<int, SystemSetting>,
     *     effective: array<string, mixed>,
     *     groups: array<int, string>,
     *     types: array<int, string>,
     *     meta: array<string, int>
     * }
     */
    public function listForApi(array $filters = [], ?int $defaultUserId = null): array
    {
        $channel = $this->normalizeChannel($filters['channel'] ?? null);
        $perPage = $this->normalizePerPage($filters['per_page'] ?? 15);

        $forUserId = isset($filters['for_user_id']) && (int) $filters['for_user_id'] > 0
            ? (int) $filters['for_user_id']
            : $defaultUserId;

        $query = $this->buildQuery($filters, $channel);

        $availableGroups = (clone $query)
            ->reorder()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->values()
            ->all();

        $settingsPaginator = $query->paginate($perPage)->withQueryString();

        /** @var Collection<int, SystemSetting> $settings */
        $settings = collect($settingsPaginator->items());

        return [
            'paginator' => $settingsPaginator,
            'settings' => $settings,
            'effective' => $this->resolveEffectiveForSettings($settings, $forUserId, $channel),
            'groups' => $availableGroups,
            'types' => $this->supportedTypes(),
            'meta' => [
                'current_page' => $settingsPaginator->currentPage(),
                'last_page' => $settingsPaginator->lastPage(),
                'per_page' => $settingsPaginator->perPage(),
                'total' => $settingsPaginator->total(),
            ],
        ];
    }

    /**
     * Build filtered settings query.
     *
     * WHY:
     * Query rules are shared business behavior for settings management.
     *
     * @param array<string, mixed> $filters
     */
    protected function buildQuery(array $filters, ?string $channel): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $group = trim((string) ($filters['group'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));

        $isActive = $this->normalizeBooleanFilter($filters['is_active'] ?? null);
        $isPublic = $this->normalizeBooleanFilter($filters['is_public'] ?? null);
        $isEncrypted = $this->normalizeBooleanFilter($filters['is_encrypted'] ?? null);

        return SystemSetting::query()
            ->with([
                'scopeUser:id,name',
                'scopeRole:id,name',
                'scopePermission:id,name',
            ])
            ->when(
                $search !== '',
                function (Builder $builder) use ($search): void {
                    $builder->where(function (Builder $nested) use ($search): void {
                        $nested
                            ->where('key', 'like', "%{$search}%")
                            ->orWhere('label', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $group !== '',
                fn (Builder $builder) => $builder->where('group', $group)
            )
            ->when(
                $type !== '',
                fn (Builder $builder) => $builder->where('type', $type)
            )
            ->when(
                $isActive !== null,
                fn (Builder $builder) => $builder->where('is_active', $isActive)
            )
            ->when(
                $isPublic !== null,
                fn (Builder $builder) => $builder->where('is_public', $isPublic)
            )
            ->when(
                $isEncrypted !== null,
                fn (Builder $builder) => $builder->where('is_encrypted', $isEncrypted)
            )
            ->when(
                $channel === SystemSetting::CHANNEL_FRONTEND,
                fn (Builder $builder) => $builder->where('is_frontend', true)
            )
            ->when(
                $channel === SystemSetting::CHANNEL_BACKEND,
                fn (Builder $builder) => $builder->where('is_backend', true)
            )
            ->orderBy('group')
            ->orderBy('key')
            ->orderByDesc('priority');
    }

    /**
     * Resolve effective settings for current list page.
     *
     * WHY:
     * Admin UI needs to preview which values win inheritance resolution.
     *
     * @param Collection<int, SystemSetting> $settings
     *
     * @return array<string, mixed>
     */
    protected function resolveEffectiveForSettings(
        Collection $settings,
        ?int $forUserId,
        ?string $channel
    ): array {
        if (!$forUserId) {
            return [];
        }

        $user = User::query()->find($forUserId);

        if (!$user) {
            return [];
        }

        $keys = $settings->pluck('key')->values()->all();

        return $this->resolver->resolveManyForUser($user, $keys, $channel);
    }

    /**
     * Get supported setting types for UI filters/forms.
     *
     * @return array<int, string>
     */
    protected function supportedTypes(): array
    {
        return [
            SystemSetting::TYPE_STRING,
            SystemSetting::TYPE_INTEGER,
            SystemSetting::TYPE_FLOAT,
            SystemSetting::TYPE_BOOLEAN,
            SystemSetting::TYPE_JSON,
            SystemSetting::TYPE_ARRAY,

            /*
            |--------------------------------------------------------------------------
            | Future UI-Specific Types
            |--------------------------------------------------------------------------
            */

            'enum',
            'color',
            'select',
            'textarea',
            'toggle',
        ];
    }

    /**
     * Normalize runtime channel identifier.
     */
    public function normalizeChannel(mixed $channel): ?string
    {
        return in_array(
            $channel,
            [
                SystemSetting::CHANNEL_FRONTEND,
                SystemSetting::CHANNEL_BACKEND,
            ],
            true
        )
            ? $channel
            : null;
    }

    /**
     * Normalize boolean filter from query params.
     */
    protected function normalizeBooleanFilter(mixed $value): ?bool
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        if ($value === true || $value === false) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Normalize pagination limit.
     */
    protected function normalizePerPage(mixed $value): int
    {
        $perPage = (int) $value;

        return min(max($perPage, 5), 100);
    }
}
