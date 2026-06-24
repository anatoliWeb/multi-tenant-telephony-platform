<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'role',
        'status',
        'access_state',
        'block_display_mode',
        'can_invite',
        'can_remove',
        'can_send',
        'can_attach',
        'can_manage',
        'can_moderate',
        'blocked_by',
        'blocked_at',
        'blocked_reason',
        'history_visibility_mode',
        'history_visible_from_message_id',
        'history_visible_from_at',
        'history_visible_until_message_id',
        'history_visible_until_at',
        'joined_at',
        'left_at',
        'removed_at',
        'last_read_message_id',
        'last_read_at',
        'muted_until',
        'metadata',
    ];

    protected $casts = [
        'can_invite' => 'boolean',
        'can_remove' => 'boolean',
        'can_send' => 'boolean',
        'can_attach' => 'boolean',
        'can_manage' => 'boolean',
        'can_moderate' => 'boolean',
        'blocked_at' => 'datetime',
        'history_visible_from_at' => 'datetime',
        'history_visible_until_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'removed_at' => 'datetime',
        'last_read_at' => 'datetime',
        'muted_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function historyVisibleFromMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'history_visible_from_message_id');
    }

    public function historyVisibleUntilMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'history_visible_until_message_id');
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }
}
