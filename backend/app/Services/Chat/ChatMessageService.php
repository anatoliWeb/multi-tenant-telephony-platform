<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageDeleted;
use App\Events\Chat\ChatMessageDeliveryUpdated;
use App\Events\Chat\ChatMessageUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatMessageService
{
    public function __construct(
        protected ChatAccessService $accessService,
        protected ChatAttachmentService $attachmentService,
        protected ChatWebhookDeliveryService $webhookDeliveryService,
        protected ChatModerationService $chatModerationService,
        protected ChatSuspiciousActivityService $chatSuspiciousActivityService,
    ) {
    }

    /**
     * Send a chat message and trigger realtime/webhook side effects.
     *
     * Side effects:
     * - updates conversation last message pointers
     * - creates delivery rows for active participants
     * - emits realtime events and queues webhook delivery events
     *
     * @param array<string, mixed> $payload
     */
    public function sendMessage(User $sender, Conversation $conversation, array $payload): Message
    {
        if (! $this->accessService->canSendMessage($sender, $conversation)) {
            throw new AuthorizationException('You are not allowed to send messages in this conversation.');
        }

        if ($conversation->status !== 'active') {
            throw ValidationException::withMessages([
                'conversation' => ['Messages can only be sent to active conversations.'],
            ]);
        }

        $type = (string) ($payload['type'] ?? 'text');
        if (! in_array($type, ['text', 'system'], true)) {
            throw ValidationException::withMessages([
                'type' => ['Only text and system message types are allowed in this phase.'],
            ]);
        }

        $body = trim((string) ($payload['body'] ?? ''));
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['Message body is required.'],
            ]);
        }

        $senderType = $sender->hasAnyPermission(['chat.admin.reply', 'chat.admin.moderate']) ? 'admin' : 'user';

        $result = DB::transaction(function () use ($sender, $conversation, $body, $type, $senderType): array {
            $message = Message::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'sender_type' => $senderType,
                'external_id' => null,
                'reply_to_message_id' => null,
                'type' => $type,
                'body' => $body,
                'status' => 'sent',
                'is_imported' => false,
                'imported_from_conversation_id' => null,
                'imported_from_message_id' => null,
                'sent_at' => now(),
                'delivered_at' => null,
                'read_at' => null,
                'edited_at' => null,
                'deleted_at' => null,
                'metadata' => null,
            ]);

            $conversation->last_message_id = $message->id;
            $conversation->last_message_at = $message->created_at;
            $conversation->save();

            $deliveries = $this->createDeliveriesForActiveParticipants($conversation, $message, $sender);

            return [
                'message' => $message->fresh(),
                'deliveries' => $deliveries,
            ];
        });

        /** @var Message $message */
        $message = $result['message'];
        /** @var array<int, MessageDelivery> $deliveries */
        $deliveries = $result['deliveries'];

        $this->chatModerationService->logMessageCreated($sender, $message, [
            'source' => 'message_lifecycle',
            'message_type' => $message->type,
            'conversation_id' => $conversation->id,
            'conversation_type' => $conversation->type,
            'conversation_source' => $conversation->source,
            'was_imported' => (bool) $message->is_imported,
            'created_by_role' => $senderType,
            'admin_reply' => $senderType === 'admin',
            'had_attachments' => false,
            'attachments_count' => 0,
        ]);

        if ($senderType === 'admin') {
            $this->chatModerationService->logAdminReplyCreated($sender, $message, [
                'source' => 'admin_reply',
                'message_type' => $message->type,
                'conversation_id' => $conversation->id,
                'conversation_type' => $conversation->type,
                'conversation_source' => $conversation->source,
                'admin_reply' => true,
                'created_by_role' => 'admin',
                'had_attachments' => false,
            ]);
        }

        // Log-only suspicious activity placeholder for future anti-abuse signals.
        $this->chatSuspiciousActivityService->inspectMessageCreated($message, $sender);

        event(new ChatMessageCreated(
            conversationId: $conversation->id,
            payload: $this->buildMessageRealtimePayload($message)
        ));
        $this->webhookDeliveryService->queueEvent(
            'message.created',
            $this->buildMessageWebhookPayload('message.created', $message)
        );

        foreach ($deliveries as $delivery) {
            event(new ChatMessageDeliveryUpdated(
                conversationId: $conversation->id,
                payload: $this->buildDeliveryRealtimePayload($delivery)
            ));

            $this->webhookDeliveryService->queueEvent(
                'message.delivery.updated',
                $this->buildDeliveryWebhookPayload($delivery)
            );
        }

        return $message;
    }

    /**
     * Edit message body when actor is owner or moderator.
     *
     * @param array<string, mixed> $payload
     */
    public function editMessage(User $actor, Message $message, array $payload): Message
    {
        if ($message->status === 'deleted' || $message->deleted_at !== null) {
            throw ValidationException::withMessages([
                'message' => ['Deleted message cannot be edited.'],
            ]);
        }

        if ($message->is_imported) {
            throw ValidationException::withMessages([
                'message' => ['Imported message cannot be edited.'],
            ]);
        }

        $conversation = $message->conversation;
        if (! $conversation) {
            throw ValidationException::withMessages([
                'message' => ['Message conversation is invalid.'],
            ]);
        }

        $isOwner = (int) $message->sender_id === (int) $actor->id;
        $isModerator = $actor->hasAnyPermission(['chat.admin.moderate']);
        if (! $isOwner && ! $isModerator) {
            throw new AuthorizationException('You are not allowed to edit this message.');
        }

        $body = trim((string) ($payload['body'] ?? ''));
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['Message body is required.'],
            ]);
        }

        $message->body = $body;
        $message->edited_at = now();
        $message->save();

        $updated = $message->fresh();
        $this->chatModerationService->logMessageUpdated($actor, $updated, [
            'source' => 'message_lifecycle',
            'message_type' => $updated->type,
            'conversation_id' => $conversation->id,
            'conversation_type' => $conversation->type,
            'conversation_source' => $conversation->source,
            'edited_fields' => ['body'],
            'was_imported' => (bool) $updated->is_imported,
        ]);

        event(new ChatMessageUpdated(
            conversationId: $conversation->id,
            payload: $this->buildMessageRealtimePayload($updated)
        ));
        $this->webhookDeliveryService->queueEvent(
            'message.updated',
            $this->buildMessageWebhookPayload('message.updated', $updated)
        );

        return $updated;
    }

    /**
     * Soft-delete message and broadcast deletion side effects.
     *
     * Body is scrubbed for privacy while attachment records remain for audit/recovery flow.
     */
    public function deleteMessage(User $actor, Message $message): Message
    {
        if ($message->status === 'deleted' || $message->deleted_at !== null) {
            return $message;
        }

        $conversation = $message->conversation;
        if (! $conversation) {
            throw ValidationException::withMessages([
                'message' => ['Message conversation is invalid.'],
            ]);
        }

        $isOwner = (int) $message->sender_id === (int) $actor->id;
        $isModerator = $actor->hasAnyPermission(['chat.admin.delete_messages', 'chat.admin.moderate']);
        if (! $isOwner && ! $isModerator) {
            throw new AuthorizationException('You are not allowed to delete this message.');
        }

        $deleted = DB::transaction(function () use ($message, $conversation, $actor): Message {
            $hadAttachments = $message->attachments()->exists();

            $message->status = 'deleted';
            $message->deleted_at = now();
            // WHY: scrub message body on soft delete to reduce sensitive text exposure.
            $message->body = null;
            $message->save();

            // WHY:
            // Keep attachment records for audit/recovery flow, but mark them deleted
            // when parent message is deleted. Physical file cleanup is deferred.
            $this->attachmentService->markAttachmentsDeletedForMessage($message, $actor);

            if ((int) $conversation->last_message_id === (int) $message->id) {
                $previousVisible = Message::query()
                    ->forCurrentTenant()
                    ->where('conversation_id', $conversation->id)
                    ->where('id', '!=', $message->id)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'deleted')
                    ->orderByDesc('id')
                    ->first();

                $conversation->last_message_id = $previousVisible?->id;
                $conversation->last_message_at = $previousVisible?->created_at;
                $conversation->save();
            }

            $fresh = $message->fresh();
            $fresh->setAttribute('had_attachments_snapshot', $hadAttachments);

            return $fresh;
        });

        $this->chatModerationService->logMessageDeleted($actor, $deleted, metadata: [
            'source' => 'message_lifecycle',
            'message_type' => $deleted->type,
            'conversation_source' => $conversation->source,
            'was_imported' => (bool) $deleted->is_imported,
            'had_attachments' => (bool) ($deleted->getAttribute('had_attachments_snapshot') ?? false),
        ]);

        event(new ChatMessageDeleted(
            conversationId: $conversation->id,
            payload: $this->buildMessageRealtimePayload($deleted)
        ));
        $this->webhookDeliveryService->queueEvent(
            'message.deleted',
            $this->buildMessageWebhookPayload('message.deleted', $deleted)
        );

        return $deleted;
    }

    /**
     * @return array<int, MessageDelivery>
     */
    private function createDeliveriesForActiveParticipants(Conversation $conversation, Message $message, User $sender): array
    {
        $participantIds = ConversationParticipant::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $userId) => $userId !== (int) $sender->id)
            ->values();

        $deliveries = [];
        foreach ($participantIds as $recipientId) {
            $delivery = MessageDelivery::query()->updateOrCreate(
                [
                    'tenant_id' => $conversation->tenant_id,
                    'message_id' => $message->id,
                    'user_id' => $recipientId,
                ],
                [
                    'tenant_id' => $conversation->tenant_id,
                    'conversation_id' => $conversation->id,
                    'external_recipient_id' => null,
                    'recipient_type' => 'user',
                    'status' => 'pending',
                    'delivered_at' => null,
                    'failed_at' => null,
                    'failure_reason' => null,
                    'metadata' => null,
                ]
            );

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    /**
     * Build sanitized realtime payload contract for message lifecycle events.
     *
     * @return array<string, mixed>
     */
    private function buildMessageRealtimePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_type' => $message->sender_type,
            'type' => $message->type,
            'status' => $message->status,
            'sent_at' => $message->sent_at?->toISOString(),
            'edited_at' => $message->edited_at?->toISOString(),
            'deleted_at' => $message->deleted_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeliveryRealtimePayload(MessageDelivery $delivery): array
    {
        return [
            'message_id' => $delivery->message_id,
            'conversation_id' => $delivery->conversation_id,
            'recipient_user_id' => $delivery->user_id,
            'recipient_type' => $delivery->recipient_type,
            'status' => $delivery->status,
            'delivered_at' => $delivery->delivered_at?->toISOString(),
            'failed_at' => $delivery->failed_at?->toISOString(),
        ];
    }

    /**
     * Build webhook payload without raw message body content.
     *
     * @return array<string, mixed>
     */
    private function buildMessageWebhookPayload(string $eventType, Message $message): array
    {
        return [
            'event' => $eventType,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'message_uuid' => $message->uuid,
            'sender_id' => $message->sender_id,
            'type' => $message->type,
            'status' => $message->status,
            'sent_at' => $message->sent_at?->toISOString(),
            'edited_at' => $message->edited_at?->toISOString(),
            'deleted_at' => $message->deleted_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeliveryWebhookPayload(MessageDelivery $delivery): array
    {
        return [
            'event' => 'message.delivery.updated',
            'conversation_id' => $delivery->conversation_id,
            'message_id' => $delivery->message_id,
            'recipient_user_id' => $delivery->user_id,
            'status' => $delivery->status,
            'delivered_at' => $delivery->delivered_at?->toISOString(),
            'read_at' => null,
            'failed_at' => $delivery->failed_at?->toISOString(),
            'updated_at' => $delivery->updated_at?->toISOString(),
        ];
    }
}
