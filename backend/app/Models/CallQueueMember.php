<?php

namespace App\Models;

use App\Enums\CallQueues\CallQueueMemberType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CallQueueMember extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'call_queue_id',
        'member_type',
        'member_id',
        'extension_id',
        'user_id',
        'priority',
        'penalty',
        'is_active',
        'is_paused',
        'paused_at',
        'pause_reason',
        'last_call_at',
        'metadata',
    ];

    protected $casts = [
        'member_type' => CallQueueMemberType::class,
        'member_id' => 'integer',
        'extension_id' => 'integer',
        'user_id' => 'integer',
        'priority' => 'integer',
        'penalty' => 'integer',
        'is_active' => 'boolean',
        'is_paused' => 'boolean',
        'paused_at' => 'datetime',
        'last_call_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function queue(): BelongsTo
    {
        return $this->belongsTo(CallQueue::class, 'call_queue_id');
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pauses(): HasMany
    {
        return $this->hasMany(QueueMemberPause::class);
    }

    public function activePause(): HasOne
    {
        return $this->hasOne(QueueMemberPause::class)->whereNull('ended_at')->latestOfMany('started_at');
    }
}
