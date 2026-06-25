<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\ContactTag;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContactQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('contacts.search.default_per_page', 20)), (int) config('contacts.search.max_per_page', 100)));
        $sort = (string) ($filters['sort'] ?? 'display_name');
        $direction = (string) ($filters['direction'] ?? 'asc');

        return $this->baseQuery($filters)
            ->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function search(array $filters): LengthAwarePaginator
    {
        return $this->paginate($filters);
    }

    public function tags(): Collection
    {
        return ContactTag::query()
            ->forCurrentTenant()
            ->orderBy('name')
            ->get();
    }

    public function lookupByPhone(string $phone): ?Contact
    {
        $tenantId = $this->tenantContext->requireTenant()->getKey();
        $normalized = $this->phoneNumberNormalizer->normalize($phone)['normalized_number'];

        return Contact::query()
            ->forTenant($tenantId)
            ->with(['phones', 'emails', 'tags'])
            ->whereHas('phones', function (Builder $query) use ($tenantId, $normalized): void {
                $query->where('tenant_id', $tenantId)
                    ->where('normalized_number', $normalized)
                    ->where('is_active', true);
            })
            ->orderByRaw("case when status = 'active' then 0 when status = 'blocked' then 1 else 2 end")
            ->orderBy('display_name')
            ->first();
    }

    private function baseQuery(array $filters): Builder
    {
        $query = Contact::query()
            ->forCurrentTenant()
            ->with(['phones', 'emails', 'tags']);

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['company'] ?? null)) {
            $query->where('company_name', 'like', '%'.$filters['company'].'%');
        }

        if (array_key_exists('has_phone', $filters) && $filters['has_phone'] !== null && $filters['has_phone'] !== '') {
            $hasPhone = filter_var($filters['has_phone'], FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('phones', fn (Builder $builder) => $builder->where('is_active', true), $hasPhone ? '>=' : '=', 1);
        }

        if (filled($filters['created_by'] ?? null)) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (filled($filters['tag'] ?? null)) {
            $tag = (string) $filters['tag'];
            $query->whereHas('tags', function (Builder $builder) use ($tag): void {
                $builder->where('slug', $tag)->orWhere('name', $tag);
            });
        }

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('display_name', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('company_name', 'like', '%'.$search.'%')
                    ->orWhereHas('phones', function (Builder $phoneQuery) use ($search): void {
                        $phoneQuery->where('raw_number', 'like', '%'.$search.'%')
                            ->orWhere('normalized_number', 'like', '%'.preg_replace('/\D+/', '', $search).'%');
                    })
                    ->orWhereHas('emails', function (Builder $emailQuery) use ($search): void {
                        $emailQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('normalized_email', 'like', '%'.mb_strtolower($search).'%');
                    });
            });
        }

        return $query;
    }
}
