<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDelivery extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_id',
        'user_id',
        'external_recipient_id',
        'recipient_type',
        'status',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
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
}
