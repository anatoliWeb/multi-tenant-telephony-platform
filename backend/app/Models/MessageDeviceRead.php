<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDeviceRead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'message_id',
        'conversation_id',
        'user_id',
        'chat_user_device_id',
        'device_key',
        'device_type',
        'platform',
        'browser',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ChatUserDevice::class, 'chat_user_device_id');
    }
}
