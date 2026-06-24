<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatUserDevice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'device_key',
        'device_name',
        'device_type',
        'platform',
        'browser',
        'app_version',
        'ip_address',
        'user_agent',
        'is_active',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messageDeviceReads(): HasMany
    {
        return $this->hasMany(MessageDeviceRead::class);
    }
}
