<?php

namespace App\Services;

use App\Actions\Users\CreateUserAction;
use App\Events\Users\UserCreated;
use App\Events\Users\UserUpdated;
use App\Enums\Rbac\RoleScope;
use App\Services\MetaCacheService;
use App\Services\Rbac\PermissionCacheService;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Observers\UserObserver;
use App\DTO\UserDTO;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * User service.
 *
 * WHY:
 * Encapsulates all user-related business logic in one place,
 * keeping controllers thin and focused on request/response handling.
 *
 * Provides a single source of truth for:
 * - user CRUD operations
 * - RBAC synchronization (roles + permissions)
 * - data transformation (DTO)
 */
class UserService
{
    public function __construct(
        protected CreateUserAction $createUserAction,
        protected PermissionCacheService $permissionCacheService,
        protected MetaCacheService $metaCacheService,
    ) {
    }

    /**
     * Convert User model to stable API DTO shape.
     *
     * WHY:
     * Frontend should receive one consistent contract across
     * list/show/create/update responses.
     */
    protected function toDto(User $user): UserDTO
    {
        return new UserDTO(
            $user->id,
            $user->name,
            $user->email,
            $user->roles->pluck('name')->values()->all(),
            $user->permissions->pluck('name')->values()->all(),
            $user->deniedPermissions->pluck('name')->values()->all(),
            $user->created_at?->toISOString(),
        );
    }

    /**
     * Get paginated users list for API.
     *
     * WHY:
     * User list endpoints should support search, filters, sorting and pagination
     * without putting query logic inside controllers.
     *
     * @param array<string, mixed> $filters
     */
    public function listForApi(array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildUsersQuery($filters);

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(
            fn (User $user) => $this->toDto($user)->toArray()
        );

        return $users;
    }

    /**
     * Build users query with API filters.
     *
     * WHY:
     * Keeping query construction in one method makes it easier to reuse
     * for API, admin pages, tests and future DTO/action layers.
     *
     * @param array<string, mixed> $filters
     */
    protected function buildUsersQuery(array $filters = []): Builder
    {
        $query = User::query()
            ->with(['roles:id,name', 'permissions:id,name', 'deniedPermissions:id,name']);

        $this->applySearch($query, $filters['search'] ?? $filters['q'] ?? null);
        $this->applyRoleFilter($query, $filters['role'] ?? null);
        $this->applyPermissionFilter($query, $filters['permission'] ?? null);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * Apply search by name or email.
     */
    protected function applySearch(Builder $query, mixed $search): void
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search): void {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Filter users by role name.
     */
    protected function applyRoleFilter(Builder $query, mixed $role): void
    {
        $role = is_string($role) ? trim($role) : '';

        if ($role === '') {
            return;
        }

        $query->whereHas('roles', function (Builder $query) use ($role): void {
            $query->where('name', $role);
        });
    }

    /**
     * Filter users by direct permission name.
     */
    protected function applyPermissionFilter(Builder $query, mixed $permission): void
    {
        $permission = is_string($permission) ? trim($permission) : '';

        if ($permission === '') {
            return;
        }

        $query->whereHas('permissions', function (Builder $query) use ($permission): void {
            $query->where('name', $permission);
        });
    }

    /**
     * Apply safe sorting.
     *
     * WHY:
     * Sorting must be whitelisted to avoid unsafe column injection.
     *
     * @param array<string, mixed> $filters
     */
    protected function applySort(Builder $query, array $filters): void
    {
        $allowedSorts = [
            'id',
            'name',
            'email',
            'created_at',
        ];

        $sort = $filters['sort'] ?? 'id';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction);
    }

    /**
     * Get all users as DTO collection.
     *
     * WHY:
     * DTO isolates API output from internal model structure
     * and prevents accidental data exposure (e.g. passwords, hidden fields).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsersForApi(): array
    {
        return User::with(['roles:id,name', 'permissions:id,name', 'deniedPermissions:id,name'])
            ->get()
            ->map(fn (User $user) => $this->toDto($user))
            ->values()
            ->all();
    }

    /**
     * Get filtered users list for frontend DataTable.
     *
     * WHY:
     * Frontend currently expects a flat array of users,
     * not Laravel paginator metadata.
     *
     * This method keeps the existing API response shape stable
     * while still allowing search, filters and safe sorting.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsersForDataTable(array $filters = []): array
    {
        return $this->buildUsersQuery($filters)
            ->get()
            ->map(fn (User $user) => $this->toDto($user)->toArray())
            ->values()
            ->all();
    }

    /**
     * Backward-compatible alias for existing calls.
     */
    public function getUsers(): array
    {
        return $this->getUsersForApi();
    }

    /**
     * Get single user as DTO.
     *
     * WHY:
     * Keeps API response consistent with list endpoint
     * and avoids exposing raw Eloquent models.
     */
    public function getUser(int $id): UserDTO
    {
        $user = User::with(['roles:id,name', 'permissions:id,name', 'deniedPermissions:id,name'])->findOrFail($id);
        return $this->toDto($user);
    }

    /**
     * Get users list for Blade admin pages.
     *
     * WHY:
     * Consistent naming improves readability and maintainability across the project.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsersForAdmin(): array
    {
        return $this->getUsersForApi();
    }

    /**
     * Get raw user model with relations.
     *
     * WHY:
     * Used internally (e.g. admin forms) where full model access is required.
     * Loads only necessary fields to optimize query performance.
     */
    public function getById(int $id): array
    {
        $user = User::with(['roles:id,name', 'permissions:id,name', 'deniedPermissions:id,name'])->findOrFail($id);
        return $this->toDto($user)->toArray();
    }

    /**
     * Create new user.
     *
     * WHY:
     * Handles:
     * - secure password hashing
     * - role assignment (RBAC)
     * - direct permission assignment
     *
     * Keeps all user creation logic centralized.
     */
    public function create(array $data): array
    {
        $user = $this->createUserAction->execute($data);

        // WHY:
        // Sync roles (many-to-many)
        // Using sync ensures full replacement (no duplicates)
        $this->syncRoles($user, $data['roles'] ?? []);

        // WHY:
        // Permissions are passed as names from frontend
        // Convert them to IDs before syncing to maintain DB integrity
        $user->permissions()->sync(
            Permission::where('scope', 'platform')
                ->whereIn('name', $data['permissions'] ?? [])
                ->pluck('id')
        );

        $user->deniedPermissions()->sync(
            Permission::where('scope', 'platform')
                ->whereIn('name', $data['denied_permissions'] ?? [])
                ->pluck('id')
        );

        $this->permissionCacheService->forgetForUser($user);
        $this->metaCacheService->bumpUserBootstrapVersion((int) $user->id);

        event(new UserCreated(
            userId: $user->id,
            userName: $user->name,
            userEmail: $user->email,
            actorId: auth()->id(),
            occurredAt: now()->toIso8601String(),
        ));

        // WHY:
        // Reload relations to return fresh state to API
        return $this->toDto(
            $user->load('roles:id,name', 'permissions:id,name', 'deniedPermissions:id,name')
        )->toArray();
    }

    /**
     * Update existing user.
     *
     * WHY:
     * Supports partial updates while preserving security:
     * - password updated only if provided
     * - roles and permissions are fully synchronized
     */
    public function update(int $id, array $data): array
    {
        $user = User::findOrFail($id);
        $isSelfUpdate = auth()->id() === $user->id;
        $changedFields = ['name', 'email'];

        // WHY:
        // Build update payload explicitly to avoid mass-assignment issues
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        // WHY:
        // Only update password if provided (nullable in request)
        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        // WHY:
        // User update now emits a domain event listener that logs activity.
        // We skip observer logging for this exact flow to avoid duplicate rows.
        UserObserver::markSkipUpdatedForUser($user->id);
        try {
            $user->update($payload);
        } finally {
            UserObserver::unmarkSkipUpdatedForUser($user->id);
        }

        if (!$isSelfUpdate) {
            // WHY:
            // Sync roles to reflect current state exactly
            $this->syncRoles($user, $data['roles'] ?? []);

            // WHY:
            // Convert permission names to IDs and sync
            $user->permissions()->sync(
                Permission::where('scope', 'platform')
                    ->whereIn('name', $data['permissions'] ?? [])
                    ->pluck('id')
            );

            $user->deniedPermissions()->sync(
                Permission::where('scope', 'platform')
                    ->whereIn('name', $data['denied_permissions'] ?? [])
                    ->pluck('id')
            );

            $changedFields[] = 'roles';
            $changedFields[] = 'permissions';
            $changedFields[] = 'denied_permissions';
        }
        // WHY:
        // Security rule: user must not be able to remove own critical permissions.
        // Even if frontend is bypassed, backend ignores self-role/self-permission edits.

        $this->permissionCacheService->forgetForUser($user);
        $this->metaCacheService->bumpUserBootstrapVersion((int) $user->id);

        event(new UserUpdated(
            userId: $user->id,
            userName: $user->name,
            userEmail: $user->email,
            actorId: auth()->id(),
            changedFields: array_values(array_unique($changedFields)),
            occurredAt: now()->toIso8601String(),
        ));

        return $this->toDto(
            $user->load('roles:id,name', 'permissions:id,name', 'deniedPermissions:id,name')
        )->toArray();
    }

    /**
     * Delete user.
     *
     * WHY:
     * Ensures relations are cleaned up before deletion
     * to maintain database integrity.
     */
    public function delete(int $id): void
    {
        $user = User::findOrFail($id);

        // WHY:
        // Detach roles before delete to avoid orphaned pivot data
        $user->roles()->detach();
        $this->permissionCacheService->forgetForUser($user);
        $this->metaCacheService->bumpUserBootstrapVersion((int) $user->id);

        $user->delete();
    }

    /**
     * Sync user roles with scope-aware pivot data.
     *
     * WHY:
     * Role assignments are tenant-aware, so the pivot must carry the
     * role's tenant scope metadata instead of attaching bare role IDs.
     *
     * @param array<int, int|string> $roleIds
     */
    protected function syncRoles(User $user, array $roleIds): void
    {
        $normalizedRoleIds = collect($roleIds)
            ->filter(fn ($roleId) => is_numeric($roleId))
            ->map(fn ($roleId) => (int) $roleId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedRoleIds === []) {
            $user->roles()->sync([]);
            return;
        }

        $roles = Role::query()
            ->whereIn('id', $normalizedRoleIds)
            ->get(['id', 'scope', 'scope_reference', 'tenant_id']);

        $syncData = [];

        foreach ($roles as $role) {
            $syncData[$role->getKey()] = [
                'scope_reference' => $this->resolveRoleScopeReference($role),
                'tenant_id' => $this->resolveRoleTenantId($role),
            ];
        }

        $user->roles()->sync($syncData);
    }

    protected function resolveRoleScopeReference(Role $role): string
    {
        $scope = $role->scope instanceof \BackedEnum ? $role->scope->value : (string) $role->scope;

        if ($scope === RoleScope::Tenant->value) {
            if (is_string($role->scope_reference) && $role->scope_reference !== '') {
                return $role->scope_reference;
            }

            if (! empty($role->tenant_id)) {
                return (string) $role->tenant_id;
            }
        }

        return RoleScope::Platform->value;
    }

    protected function resolveRoleTenantId(Role $role): ?string
    {
        $scope = $role->scope instanceof \BackedEnum ? $role->scope->value : (string) $role->scope;

        if ($scope !== RoleScope::Tenant->value) {
            return null;
        }

        return $role->tenant_id ? (string) $role->tenant_id : null;
    }
}
