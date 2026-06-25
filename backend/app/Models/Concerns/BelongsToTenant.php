<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\Tenancy\TenantBootstrapService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->isFillable('tenant_id') || filled($model->getAttribute('tenant_id'))) {
                return;
            }

            $tenantId = self::resolveTenantIdForWrite();
            if ($tenantId !== null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, Tenant|string|null $tenant = null): Builder
    {
        $tenantId = $tenant instanceof Tenant
            ? (string) $tenant->getKey()
            : (is_string($tenant) && $tenant !== '' ? $tenant : self::resolveTenantIdForRead());

        if ($tenantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        return $this->scopeForTenant($query);
    }

    public function isInCurrentTenant(): bool
    {
        $tenantId = self::resolveTenantIdForRead();

        if ($tenantId === null) {
            return false;
        }

        return (string) $this->getAttribute('tenant_id') === $tenantId;
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?? $this->getRouteKeyName();

        return $this->newQuery()
            ->forTenant()
            ->where($field, $value)
            ->first();
    }

    protected static function resolveTenantIdForRead(): ?string
    {
        $tenantContext = app()->bound(TenantContext::class)
            ? app(TenantContext::class)
            : null;

        $tenantId = $tenantContext?->tenantId();
        if (is_string($tenantId) && $tenantId !== '') {
            return $tenantId;
        }

        $request = request();
        $identifier = trim((string) $request?->header('X-Tenant-ID', ''));
        if ($identifier !== '') {
            $tenant = app(TenantBootstrapService::class)->resolveTenantByIdentifier($identifier);

            return $tenant ? (string) $tenant->getKey() : null;
        }

        if (app()->runningUnitTests()) {
            return TenantBootstrapService::DEFAULT_TENANT_UUID;
        }

        return null;
    }

    protected static function resolveTenantIdForWrite(): ?string
    {
        return static::resolveTenantIdForRead();
    }
}
