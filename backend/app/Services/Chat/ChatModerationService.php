<?php

namespace App\Services\Chat;

use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\ChatWebhookDelivery;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatModerationService
{
    public function __construct(
        private readonly ChatActivityIntegrationService $chatActivityIntegrationService,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logMessageCreated(User $actor, Message $message, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'message.created',
            conversation: $message->conversation,
            message: $message,
            targetUserId: $message->sender_id ? (int) $message->sender_id : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logMessageUpdated(User $actor, Message $message, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'message.updated',
            conversation: $message->conversation,
            message: $message,
            targetUserId: $message->sender_id ? (int) $message->sender_id : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logMessageDeleted(User $actor, Message $message, ?string $reason = null, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'message.deleted',
            conversation: $message->conversation,
            message: $message,
            targetUserId: $message->sender_id ? (int) $message->sender_id : null,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logMessageImported(User $actor, ?Message $message = null, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'message.imported',
            conversation: $message?->conversation,
            message: $message,
            targetUserId: $message?->sender_id ? (int) $message->sender_id : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logExternalMessageCreated(?User $actor, Message $message, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'message.external_created',
            conversation: $message->conversation,
            message: $message,
            targetUserId: $message->sender_id ? (int) $message->sender_id : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logConversationClosed(User $actor, Conversation $conversation, ?string $reason = null, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'conversation.closed',
            conversation: $conversation,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logConversationArchived(User $actor, Conversation $conversation, ?string $reason = null, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'conversation.archived',
            conversation: $conversation,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logParticipantRestricted(
        User $actor,
        ConversationParticipant $participant,
        string $action,
        ?string $reason = null,
        array $metadata = []
    ): ChatModerationLog {
        return $this->createLog(
            actor: $actor,
            action: $action,
            conversation: $participant->conversation,
            targetUserId: (int) $participant->user_id,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logParticipantAccessChanged(
        User $actor,
        ConversationParticipant $participant,
        string $oldState,
        string $newState,
        array $metadata = [],
        ?string $action = null
    ): ChatModerationLog {
        $resolvedAction = $action ?? match (true) {
            $newState === 'blocked' => 'participant.blocked',
            $newState === 'read_only' => 'participant.read_only',
            $newState === 'hidden' => 'participant.hidden',
            $oldState === 'hidden' && $newState === 'full' => 'participant.visible_restored',
            $oldState === 'blocked' && $newState === 'full' => 'participant.unblocked',
            $newState === 'full' => 'participant.full_access_restored',
            default => 'participant.access_changed',
        };

        $metadata['participant_id'] = $participant->id;
        $metadata['target_user_id'] = $participant->user_id;
        $metadata['old_access_state'] = $oldState;
        $metadata['new_access_state'] = $newState;

        return $this->logParticipantRestricted(
            actor: $actor,
            participant: $participant,
            action: $resolvedAction,
            reason: null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logParticipantBlocked(
        User $actor,
        ConversationParticipant $participant,
        ?string $reason = null,
        array $metadata = []
    ): ChatModerationLog {
        return $this->logParticipantAccessChanged(
            actor: $actor,
            participant: $participant,
            oldState: (string) ($metadata['old_access_state'] ?? $participant->access_state ?? 'full'),
            newState: 'blocked',
            metadata: $metadata,
            action: 'participant.blocked',
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logParticipantUnblocked(
        User $actor,
        ConversationParticipant $participant,
        array $metadata = []
    ): ChatModerationLog {
        return $this->logParticipantAccessChanged(
            actor: $actor,
            participant: $participant,
            oldState: (string) ($metadata['old_access_state'] ?? 'blocked'),
            newState: 'full',
            metadata: $metadata,
            action: 'participant.unblocked',
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logParticipantHidden(
        User $actor,
        ConversationParticipant $participant,
        ?string $reason = null,
        array $metadata = []
    ): ChatModerationLog {
        return $this->logParticipantAccessChanged(
            actor: $actor,
            participant: $participant,
            oldState: (string) ($metadata['old_access_state'] ?? $participant->access_state ?? 'full'),
            newState: 'hidden',
            metadata: $metadata,
            action: 'participant.hidden',
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logParticipantReadOnly(
        User $actor,
        ConversationParticipant $participant,
        ?string $reason = null,
        array $metadata = []
    ): ChatModerationLog {
        return $this->logParticipantAccessChanged(
            actor: $actor,
            participant: $participant,
            oldState: (string) ($metadata['old_access_state'] ?? $participant->access_state ?? 'full'),
            newState: 'read_only',
            metadata: $metadata,
            action: 'participant.read_only',
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logAdminReplyCreated(User $actor, Message $message, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'message.admin_reply_created',
            conversation: $message->conversation,
            message: $message,
            targetUserId: $message->sender_id ? (int) $message->sender_id : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logConversationCreated(
        User $actor,
        Conversation $conversation,
        array $metadata = [],
        string $action = 'conversation.created'
    ): ChatModerationLog {
        return $this->createLog(
            actor: $actor,
            action: $action,
            conversation: $conversation,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logConversationLeft(
        User $actor,
        Conversation $conversation,
        ConversationParticipant $participant,
        array $metadata = []
    ): ChatModerationLog {
        return $this->createLog(
            actor: $actor,
            action: 'conversation.left',
            conversation: $conversation,
            targetUserId: (int) $participant->user_id,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logConversationVisibilityChanged(
        User $actor,
        Conversation $conversation,
        string $oldVisibility,
        string $newVisibility,
        array $metadata = []
    ): ChatModerationLog {
        $metadata['old_visibility'] = $oldVisibility;
        $metadata['new_visibility'] = $newVisibility;

        return $this->createLog(
            actor: $actor,
            action: 'conversation.visibility_changed',
            conversation: $conversation,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logConversationStatusChanged(
        User $actor,
        Conversation $conversation,
        string $oldStatus,
        string $newStatus,
        array $metadata = []
    ): ChatModerationLog {
        $metadata['old_status'] = $oldStatus;
        $metadata['new_status'] = $newStatus;

        return $this->createLog(
            actor: $actor,
            action: 'conversation.status_changed',
            conversation: $conversation,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logHistoryImported(
        User $actor,
        Conversation $sourceConversation,
        Conversation $targetConversation,
        array $metadata = []
    ): ChatModerationLog {
        $metadata = array_merge([
            'source_conversation_id' => $sourceConversation->id,
            'target_conversation_id' => $targetConversation->id,
            'source_conversation_type' => $sourceConversation->type,
            'target_conversation_type' => $targetConversation->type,
            'target_visibility' => $targetConversation->visibility,
            'target_participants_count' => $targetConversation->participants()->where('status', 'active')->count(),
        ], $metadata);

        return $this->createLog(
            actor: $actor,
            action: 'history.imported',
            conversation: $targetConversation,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logAttachmentUploaded(User $actor, MessageAttachment $attachment, array $metadata = []): ChatModerationLog
    {
        $metadata['attachment_id'] = $attachment->id;
        $metadata['message_id'] = $attachment->message_id;
        $metadata['conversation_id'] = $attachment->conversation_id;
        $metadata['uploaded_by_user_id'] = $attachment->uploaded_by;

        return $this->createLog(
            actor: $actor,
            action: 'attachment.uploaded',
            conversation: $attachment->conversation,
            message: $attachment->message,
            targetUserId: $attachment->uploaded_by ? (int) $attachment->uploaded_by : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logAttachmentDeleted(?User $actor, MessageAttachment $attachment, array $metadata = []): ChatModerationLog
    {
        $metadata['attachment_id'] = $attachment->id;
        $metadata['message_id'] = $attachment->message_id;
        $metadata['conversation_id'] = $attachment->conversation_id;
        $metadata['uploaded_by_user_id'] = $attachment->uploaded_by;

        return $this->createLog(
            actor: $actor,
            action: 'attachment.deleted',
            conversation: $attachment->conversation,
            message: $attachment->message,
            targetUserId: $attachment->uploaded_by ? (int) $attachment->uploaded_by : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logWebhookDeliveryCreated(ChatWebhookDelivery $delivery, array $metadata = []): ChatModerationLog
    {
        return $this->logWebhookDeliveryAction($delivery, 'webhook.delivery.created', $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logWebhookDeliverySent(ChatWebhookDelivery $delivery, array $metadata = []): ChatModerationLog
    {
        return $this->logWebhookDeliveryAction($delivery, 'webhook.delivery.sent', $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logWebhookDeliveryRetrying(ChatWebhookDelivery $delivery, array $metadata = []): ChatModerationLog
    {
        return $this->logWebhookDeliveryAction($delivery, 'webhook.delivery.retrying', $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logWebhookDeliveryFailed(ChatWebhookDelivery $delivery, array $metadata = []): ChatModerationLog
    {
        return $this->logWebhookDeliveryAction($delivery, 'webhook.delivery.failed', $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logWebhookDeliveryCancelled(ChatWebhookDelivery $delivery, array $metadata = []): ChatModerationLog
    {
        return $this->logWebhookDeliveryAction($delivery, 'webhook.delivery.cancelled', $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logSuspiciousMessageActivity(?User $actor, Message $message, array $metadata = []): ChatModerationLog
    {
        return $this->createLog(
            actor: $actor,
            action: 'suspicious.message_activity',
            conversation: $message->conversation,
            message: $message,
            targetUserId: $message->sender_id ? (int) $message->sender_id : null,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function sanitizeMetadata(array $metadata): array
    {
        $blockedKeys = [
            'token',
            'secret',
            'token_hash',
            'signing_secret',
            'signature',
            'raw_payload',
            'payload_raw',
            'raw_response',
            'response_raw',
            'response_body',
            'authorization',
            'device_key',
            'user_agent',
            'ip_address',
            'disk',
            'path',
            'checksum',
            'webhook_secret',
        ];

        $safe = [];
        foreach ($metadata as $key => $value) {
            $isStringKey = is_string($key);

            if ($isStringKey && in_array(strtolower($key), $blockedKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $sanitizedNested = $this->sanitizeMetadata($value);
                if ($isStringKey) {
                    $safe[$key] = $sanitizedNested;
                } else {
                    $safe[] = $sanitizedNested;
                }
                continue;
            }

            if (is_scalar($value) || $value === null) {
                if ($isStringKey) {
                    $safe[$key] = $value;
                } else {
                    $safe[] = $value;
                }
            }
        }

        return $safe;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function logWebhookDeliveryAction(ChatWebhookDelivery $delivery, string $action, array $metadata = []): ChatModerationLog
    {
        $metadata = array_merge([
            'webhook_delivery_id' => $delivery->id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'conversation_id' => $delivery->conversation_id,
            'message_id' => $delivery->message_id,
            'event_type' => $delivery->event,
            'status' => $delivery->status,
            'attempts' => (int) $delivery->attempts,
            'response_status' => $delivery->response_status,
            'next_retry_at' => $delivery->next_retry_at?->toISOString(),
            'sent_at' => $delivery->sent_at?->toISOString(),
            'failed_at' => $delivery->failed_at?->toISOString(),
            'endpoint_name' => $delivery->endpoint?->name,
        ], $metadata);

        return $this->createLog(
            actor: null,
            action: $action,
            conversation: $delivery->conversation,
            message: $delivery->message,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function createLog(
        ?User $actor,
        string $action,
        ?Conversation $conversation = null,
        ?Message $message = null,
        ?int $targetUserId = null,
        ?string $reason = null,
        array $metadata = []
    ): ChatModerationLog {
        $safeMetadata = $this->sanitizeMetadata($metadata);

        $log = ChatModerationLog::query()->create([
            'conversation_id' => $conversation?->id,
            'message_id' => $message?->id,
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'reason' => $reason,
            'old_values' => null,
            'new_values' => null,
            'metadata' => $safeMetadata === [] ? null : $safeMetadata,
        ]);

        try {
            $this->chatActivityIntegrationService->recordFromModerationLog($log);
        } catch (Throwable $exception) {
            Log::warning('Chat moderation activity relay failed', [
                'chat_moderation_log_id' => $log->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }

        return $log;
    }
}
