<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatWebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_endpoint_id',
        'conversation_id',
        'message_id',
        'event',
        'delivery_uuid',
        'payload',
        'signature',
        'status',
        'attempts',
        'next_retry_at',
        'sent_at',
        'failed_at',
        'response_status',
        'response_body',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_body' => 'array',
        'next_retry_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ChatWebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
