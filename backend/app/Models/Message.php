<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'sender_type',
        'external_id',
        'reply_to_message_id',
        'type',
        'body',
        'status',
        'is_imported',
        'imported_from_conversation_id',
        'imported_from_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'edited_at',
        'deleted_at',
        'metadata',
    ];

    protected $casts = [
        'is_imported' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function importedFromConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'imported_from_conversation_id');
    }

    public function importedFromMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'imported_from_message_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function deviceReads(): HasMany
    {
        return $this->hasMany(MessageDeviceRead::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
