<?php

namespace App\Models;

use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\RingGroups\RingGroupStrategy;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RingGroup extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
        'strategy',
        'status',
        'ring_timeout_seconds',
        'max_ring_duration_seconds',
        'failover_destination_type',
        'failover_destination_id',
        'settings',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'strategy' => RingGroupStrategy::class,
        'status' => RingGroupStatus::class,
        'ring_timeout_seconds' => 'integer',
        'max_ring_duration_seconds' => 'integer',
        'failover_destination_id' => 'integer',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(RingGroupMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
