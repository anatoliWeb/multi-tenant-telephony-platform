<?php

namespace App\Services\CallLogs;

use App\Models\CallLog;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CallQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $sort = (string) ($filters['sort'] ?? 'started_at');
        $direction = strtolower((string) ($filters['direction_sort'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['started_at', 'answered_at', 'ended_at', 'status', 'direction', 'talk_seconds', 'total_seconds'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'started_at';

        $query = $this->visibleQuery($user)
            ->with([
                'callerUser',
                'calleeUser',
                'callerExtension',
                'calleeExtension',
                'callerPhoneNumber',
                'calleePhoneNumber',
                'callerContact',
                'calleeContact',
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findVisible(User $user, CallLog $callLog): ?CallLog
    {
        return $this->visibleQuery($user)
            ->with([
                'callerUser',
                'calleeUser',
                'callerExtension',
                'calleeExtension',
                'callerPhoneNumber',
                'calleePhoneNumber',
                'callerContact',
                'calleeContact',
                'events',
            ])
            ->whereKey($callLog->getKey())
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function userFilterOptions(): Collection
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

    public function visibleQuery(User $user): Builder
    {
        $query = CallLog::query()->forCurrentTenant();

        if ($user->hasPermission('call_logs.view_all')) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('caller_user_id', $user->getKey())
                ->orWhere('callee_user_id', $user->getKey());
        });
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $normalizedDigits = preg_replace('/\D+/', '', $search) ?? '';
            $query->where(function (Builder $builder) use ($search, $normalizedDigits): void {
                $builder->where('from_number', 'like', '%'.$search.'%')
                    ->orWhere('to_number', 'like', '%'.$search.'%')
                    ->orWhere('from_normalized_number', 'like', '%'.$normalizedDigits.'%')
                    ->orWhere('to_normalized_number', 'like', '%'.$normalizedDigits.'%')
                    ->orWhereHas('callerContact', fn (Builder $contactQuery) => $contactQuery->where('display_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('calleeContact', fn (Builder $contactQuery) => $contactQuery->where('display_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('callerUser', fn (Builder $userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('calleeUser', fn (Builder $userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        foreach (['direction', 'status', 'disposition', 'provider_id'] as $field) {
            $inputKey = $field === 'provider_id' ? 'provider' : $field;
            $value = trim((string) ($filters[$inputKey] ?? ''));
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        $user = $filters['user'] ?? null;
        if ($user !== null && $user !== '') {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('caller_user_id', (int) $user)
                    ->orWhere('callee_user_id', (int) $user);
            });
        }

        $extension = $filters['extension'] ?? null;
        if ($extension !== null && $extension !== '') {
            $query->where(function (Builder $builder) use ($extension): void {
                $builder->where('caller_extension_id', (int) $extension)
                    ->orWhere('callee_extension_id', (int) $extension);
            });
        }

        $did = $filters['did'] ?? null;
        if ($did !== null && $did !== '') {
            $query->where(function (Builder $builder) use ($did): void {
                $builder->where('caller_phone_number_id', (int) $did)
                    ->orWhere('callee_phone_number_id', (int) $did);
            });
        }

        $contact = $filters['contact'] ?? null;
        if ($contact !== null && $contact !== '') {
            $query->where(function (Builder $builder) use ($contact): void {
                $builder->where('caller_contact_id', (int) $contact)
                    ->orWhere('callee_contact_id', (int) $contact);
            });
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->where(function (Builder $builder) use ($from): void {
                $builder->where('from_number', 'like', '%'.$from.'%')
                    ->orWhere('from_normalized_number', 'like', '%'.preg_replace('/\D+/', '', $from).'%');
            });
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->where(function (Builder $builder) use ($to): void {
                $builder->where('to_number', 'like', '%'.$to.'%')
                    ->orWhere('to_normalized_number', 'like', '%'.preg_replace('/\D+/', '', $to).'%');
            });
        }

        if (filled($filters['answered'] ?? null)) {
            $answered = filter_var($filters['answered'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($answered === true) {
                $query->whereNotNull('answered_at');
            }
            if ($answered === false) {
                $query->whereNull('answered_at');
            }
        }

        $dateFrom = $this->parseDate($filters['date_from'] ?? null, false);
        if ($dateFrom) {
            $query->whereDate('started_at', '>=', $dateFrom->toDateString());
        }

        $dateTo = $this->parseDate($filters['date_to'] ?? null, true);
        if ($dateTo) {
            $query->whereDate('started_at', '<=', $dateTo->toDateString());
        }
    }

    private function parseDate(mixed $value, bool $endOfDay): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = CarbonImmutable::parse($value);

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
