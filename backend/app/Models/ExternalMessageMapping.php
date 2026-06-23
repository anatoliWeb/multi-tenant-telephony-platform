<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalMessageMapping extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'provider',
        'external_id',
        'external_conversation_id',
        'direction',
        'idempotency_key',
        'payload_hash',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
