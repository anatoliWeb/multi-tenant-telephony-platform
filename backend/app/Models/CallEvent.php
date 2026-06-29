<?php

namespace App\Models;

use App\Enums\CallLogs\CallEventType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'call_log_id',
        'provider_event_id',
        'provider_id',
        'type',
        'occurred_at',
        'sequence',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'type' => CallEventType::class,
        'occurred_at' => 'datetime',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class);
    }
}
