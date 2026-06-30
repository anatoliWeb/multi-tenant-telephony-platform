<?php

namespace App\Services\Ivr;

use App\Enums\Ivr\IvrMenuStatus;
use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\TenantMembershipStatus;
use App\Models\CallQueue;
use App\Models\Extension;
use App\Models\IvrMenu;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IvrMenuQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $query = IvrMenu::query()
            ->forCurrentTenant()
            ->withCount(['options', 'activeOptions'])
            ->orderBy('name');

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

        return $query->paginate($perPage);
    }

    /**
     * @return array{
     *   extensions: Collection<int, Extension>,
     *   ring_groups: Collection<int, RingGroup>,
     *   call_queues: Collection<int, CallQueue>,
     *   ivr_menus: Collection<int, IvrMenu>,
     *   statuses: array<int, string>,
     *   destination_types: array<int, string>,
     *   actions: array<int, string>,
     *   digits: array<int, string>
     * }
     */
    public function options(): array
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();

        return [
            'extensions' => Extension::query()
                ->forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('number')
                ->get(['id', 'number', 'label', 'status']),
            'ring_groups' => RingGroup::query()
                ->forTenant($tenantId)
                ->where('status', RingGroupStatus::Active->value)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status']),
            'call_queues' => CallQueue::query()
                ->forTenant($tenantId)
                ->where('status', CallQueueStatus::Active->value)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status']),
            'ivr_menus' => IvrMenu::query()
                ->forTenant($tenantId)
                ->where('status', IvrMenuStatus::Active->value)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status']),
            'statuses' => IvrMenuStatus::values(),
            'destination_types' => ['extension', 'ring_group', 'call_queue', 'ivr_menu', 'hangup', 'voicemail_placeholder'],
            'actions' => ['repeat', 'route', 'hangup'],
            'digits' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '#'],
        ];
    }
}
