<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'visibility',
        'title',
        'description',
        'owner_id',
        'created_by',
        'created_from_conversation_id',
        'source',
        'status',
        'join_policy',
        'history_import_mode',
        'history_import_from_message_id',
        'history_import_from_at',
        'last_message_id',
        'last_message_at',
        'metadata',
    ];

    protected $casts = [
        'history_import_from_at' => 'datetime',
        'last_message_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceConversation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_from_conversation_id');
    }

    public function historyImportFromMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'history_import_from_message_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
