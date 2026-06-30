<?php

namespace App\Models;

use App\Enums\RingGroups\RingGroupMemberType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RingGroupMember extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'ring_group_id',
        'member_type',
        'extension_id',
        'user_id',
        'priority',
        'delay_seconds',
        'timeout_seconds',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'member_type' => RingGroupMemberType::class,
        'extension_id' => 'integer',
        'user_id' => 'integer',
        'priority' => 'integer',
        'delay_seconds' => 'integer',
        'timeout_seconds' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function ringGroup(): BelongsTo
    {
        return $this->belongsTo(RingGroup::class);
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
