<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueMemberPause extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'call_queue_id',
        'call_queue_member_id',
        'user_id',
        'started_at',
        'ended_at',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function queue(): BelongsTo
    {
        return $this->belongsTo(CallQueue::class, 'call_queue_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CallQueueMember::class, 'call_queue_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
