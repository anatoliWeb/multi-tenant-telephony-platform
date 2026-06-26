<?php

namespace App\Services\PhoneNumbers;

use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PhoneNumberQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sort = (string) ($filters['sort'] ?? 'display_number');
        $sort = in_array($sort, ['display_number', 'normalized_number', 'status', 'type', 'created_at'], true)
            ? $sort
            : 'display_number';

        $query = PhoneNumber::query()
            ->forCurrentTenant()
            ->with(['assignedUser.assignedExtensions' => fn ($builder) => $builder
                ->where('tenant_id', (string) $this->tenantContext->requireTenant()->getKey())
                ->orderBy('number')])
            ->orderBy($sort, $direction);

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, User>
     */
    public function assignmentOptions(): Collection
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();

        return User::query()
            ->whereHas('tenantMemberships', fn (Builder $builder) => $builder
                ->where('tenant_id', $tenantId)
                ->where('status', 'active'))
            ->with(['assignedExtensions' => fn ($builder) => $builder
                ->where('tenant_id', $tenantId)
                ->orderBy('number')])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * @return Collection<int, PhoneNumber>
     */
    public function phoneNumbersForUser(User $user): Collection
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();
        $this->assertUserBelongsToTenant($user, $tenantId);

        return PhoneNumber::query()
            ->forTenant($tenantId)
            ->where('assigned_user_id', $user->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('display_number')
            ->with(['assignedUser.assignedExtensions' => fn ($builder) => $builder
                ->where('tenant_id', $tenantId)
                ->orderBy('number')])
            ->get();
    }

    public function assertUserBelongsToTenant(User $user, string $tenantId): void
    {
        $belongs = $user->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $belongs) {
            abort(404, 'User not found.');
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('number', 'like', '%'.$search.'%')
                    ->orWhere('display_number', 'like', '%'.$search.'%')
                    ->orWhere('normalized_number', 'like', '%'.$search.'%')
                    ->orWhereHas('assignedUser', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%'));
            });
        }

        $number = trim((string) ($filters['number'] ?? ''));
        if ($number !== '') {
            $query->where(function (Builder $builder) use ($number): void {
                $builder
                    ->where('number', 'like', '%'.$number.'%')
                    ->orWhere('normalized_number', 'like', '%'.$number.'%');
            });
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $provider = trim((string) ($filters['provider'] ?? ''));
        if ($provider !== '') {
            $query->where('provider_name', $provider);
        }

        $assigned = $filters['assigned'] ?? null;
        if ($assigned === true || $assigned === 'true' || $assigned === 'assigned') {
            $query->whereNotNull('assigned_user_id');
        }

        if ($assigned === false || $assigned === 'false' || $assigned === 'unassigned') {
            $query->whereNull('assigned_user_id');
        }

        $assignedUser = $filters['assigned_user'] ?? null;
        if ($assignedUser !== null && $assignedUser !== '') {
            $query->where('assigned_user_id', (int) $assignedUser);
        }

        $primary = $filters['primary'] ?? null;
        if ($primary === true || $primary === 'true' || $primary === '1') {
            $query->where('is_primary', true);
        }
    }
}
