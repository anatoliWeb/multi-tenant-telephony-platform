<?php

namespace App\Services\Extensions;

use App\Models\Contact;
use App\Models\Extension;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExtensionQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $query = Extension::query()
            ->forCurrentTenant()
            ->with(['credential', 'assignedUser', 'assignedContact'])
            ->orderBy('number');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @return array{users: Collection<int, User>, contacts: Collection<int, Contact>}
     */
    public function assignmentOptions(): array
    {
        $tenantId = (string) $this->tenantContext->requireTenant()->getKey();

        return [
            'users' => User::query()
                ->whereHas('tenantMemberships', fn (Builder $builder) => $builder
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'contacts' => Contact::query()
                ->forTenant($tenantId)
                ->orderBy('display_name')
                ->get(['id', 'tenant_id', 'display_name', 'company_name']),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('number', 'like', '%'.$search.'%')
                    ->orWhere('label', 'like', '%'.$search.'%')
                    ->orWhereHas('assignedUser', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('assignedContact', fn (Builder $contact) => $contact->where('display_name', 'like', '%'.$search.'%'));
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $assigned = trim((string) ($filters['assigned'] ?? ''));
        if ($assigned === 'user') {
            $query->whereNotNull('assigned_user_id');
        }

        if ($assigned === 'contact') {
            $query->whereNotNull('assigned_contact_id');
        }

        if ($assigned === 'unassigned') {
            $query->whereNull('assigned_user_id')->whereNull('assigned_contact_id');
        }
    }
}
