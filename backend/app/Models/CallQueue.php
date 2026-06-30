<?php

namespace App\Models;

use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\CallQueues\CallQueueStrategy;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallQueue extends Model
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
        'max_wait_time_seconds',
        'ring_timeout_seconds',
        'retry_delay_seconds',
        'max_attempts',
        'music_on_hold',
        'announce_position',
        'announce_estimated_wait',
        'overflow_destination_type',
        'overflow_destination_id',
        'settings',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'strategy' => CallQueueStrategy::class,
        'status' => CallQueueStatus::class,
        'max_wait_time_seconds' => 'integer',
        'ring_timeout_seconds' => 'integer',
        'retry_delay_seconds' => 'integer',
        'max_attempts' => 'integer',
        'announce_position' => 'boolean',
        'announce_estimated_wait' => 'boolean',
        'overflow_destination_id' => 'integer',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(CallQueueMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function pauseHistory(): HasMany
    {
        return $this->hasMany(QueueMemberPause::class);
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
