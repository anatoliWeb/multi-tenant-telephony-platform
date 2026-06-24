<?php

namespace App\Models;

use App\Enums\TenantMembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantMembership extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'status',
        'invited_by',
        'invited_at',
        'accepted_at',
        'activated_at',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantMembershipStatus::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TenantMembership $membership): void {
            if (blank($membership->getKey())) {
                $membership->setAttribute($membership->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
