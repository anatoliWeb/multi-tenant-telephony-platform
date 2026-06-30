<?php

namespace App\Services\CallQueues;

use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\CallQueues\CallQueueStrategy;
use App\Enums\TenantMembershipStatus;
use App\Models\CallQueue;
use App\Models\Extension;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CallQueueQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $query = CallQueue::query()
            ->forCurrentTenant()
            ->withCount([
                'members',
                'activeMembers',
                'members as paused_members_count' => fn (Builder $builder) => $builder->where('is_paused', true),
            ])
            ->orderBy('name');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @return array{
     *   extensions: Collection<int, Extension>,
     *   users: Collection<int, User>,
     *   queues: Collection<int, CallQueue>,
     *   ring_groups: Collection<int, RingGroup>,
     *   strategies: array<int, string>,
     *   statuses: array<int, string>,
     *   overflow_destinations: array<int, array{id:string,label:string,items:array<int, array{id:int,label:string,sub_label:string|null}>}>
     * }
     */
    public function options(): array
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();

        $extensions = Extension::query()
            ->forTenant($tenantId)
            ->where('status', 'active')
            ->orderBy('number')
            ->get(['id', 'tenant_id', 'number', 'label', 'status']);

        $users = User::query()
            ->whereHas('tenantMemberships', fn (Builder $builder) => $builder
                ->where('tenant_id', $tenantId)
                ->where('status', TenantMembershipStatus::Active->value))
            ->with(['assignedExtensions' => fn ($builder) => $builder->where('tenant_id', $tenantId)->orderBy('number')])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $queues = CallQueue::query()
            ->forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status']);

        $ringGroups = RingGroup::query()
            ->forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status']);

        return [
            'extensions' => $extensions,
            'users' => $users,
            'queues' => $queues,
            'ring_groups' => $ringGroups,
            'strategies' => CallQueueStrategy::values(),
            'statuses' => CallQueueStatus::values(),
            'overflow_destinations' => [
                [
                    'id' => 'extension',
                    'label' => 'Extensions',
                    'items' => $extensions->map(fn (Extension $extension): array => [
                        'id' => $extension->id,
                        'label' => $extension->number.($extension->label ? ' - '.$extension->label : ''),
                        'sub_label' => $extension->status?->value ?? $extension->status,
                    ])->values()->all(),
                ],
                [
                    'id' => 'user',
                    'label' => 'Users',
                    'items' => $users->map(fn (User $user): array => [
                        'id' => $user->id,
                        'label' => $user->name,
                        'sub_label' => $user->email,
                    ])->values()->all(),
                ],
                [
                    'id' => 'queue',
                    'label' => 'Call Queues',
                    'items' => $queues->map(fn (CallQueue $queue): array => [
                        'id' => $queue->id,
                        'label' => $queue->name,
                        'sub_label' => $queue->slug,
                    ])->values()->all(),
                ],
                [
                    'id' => 'ring_group',
                    'label' => 'Ring Groups',
                    'items' => $ringGroups->map(fn (RingGroup $ringGroup): array => [
                        'id' => $ringGroup->id,
                        'label' => $ringGroup->name,
                        'sub_label' => $ringGroup->slug,
                    ])->values()->all(),
                ],
            ],
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
