<?php

namespace App\Services\RingGroups;

use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\RingGroups\RingGroupStrategy;
use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantMembershipStatus;
use App\Models\Extension;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RingGroupQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $query = RingGroup::query()
            ->forCurrentTenant()
            ->withCount(['members', 'activeMembers'])
            ->with(['members.extension', 'members.user'])
            ->orderBy('name');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @return array{
     *   extensions: Collection<int, Extension>,
     *   users: Collection<int, User>,
     *   strategies: array<int, string>,
     *   statuses: array<int, string>
     * }
     */
    public function options(): array
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();

        return [
            'extensions' => Extension::query()
                ->forTenant($tenantId)
                ->where('status', ExtensionStatus::Active->value)
                ->orderBy('number')
                ->get(['id', 'tenant_id', 'number', 'label', 'status']),
            'users' => User::query()
                ->whereHas('tenantMemberships', fn (Builder $builder) => $builder
                    ->where('tenant_id', $tenantId)
                    ->where('status', TenantMembershipStatus::Active->value))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'strategies' => RingGroupStrategy::values(),
            'statuses' => RingGroupStatus::values(),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $strategy = trim((string) ($filters['strategy'] ?? ''));
        if ($strategy !== '') {
            $query->where('strategy', $strategy);
        }
    }
}
