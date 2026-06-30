<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'status',
        'timezone',
        'locale',
        'currency',
        'settings',
        'activated_at',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (blank($tenant->getKey())) {
                $tenant->setAttribute($tenant->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->withPivot([
                'id',
                'status',
                'invited_by',
                'invited_at',
                'accepted_at',
                'activated_at',
                'suspended_at',
                'created_at',
                'updated_at',
            ])
            ->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }

    public function ringGroups(): HasMany
    {
        return $this->hasMany(RingGroup::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }
}
