<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatHistoryImportService
{
    private const MODES = ['none', 'from_date', 'from_message', 'full'];

    public function __construct(
        protected ChatModerationService $chatModerationService,
    ) {
    }

    public function importHistory(
        User $actor,
        Conversation $sourceConversation,
        Conversation $targetConversation,
        string $mode,
        ?int $fromMessageId = null,
        ?string $fromDate = null
    ): int {
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages([
                'history_import_mode' => ['Invalid history import mode.'],
            ]);
        }

        $fromDateValue = $fromDate;
        $fromMessageValue = $fromMessageId;

        if ($mode === 'none') {
            $this->logImport(
                actor: $actor,
                sourceConversation: $sourceConversation,
                targetConversation: $targetConversation,
                mode: $mode,
                importedCount: 0,
                importedAttachmentsCount: 0,
                fromMessageId: null,
                fromDate: null,
            );

            return 0;
        }

        $sourceQuery = Message::query()
            ->where('conversation_id', $sourceConversation->id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'deleted')
            ->orderBy('id');

        if ($mode === 'from_date') {
            if (! $fromDate) {
                throw ValidationException::withMessages([
                    'history_import_from_at' => ['history_import_from_at is required for from_date mode.'],
                ]);
            }
            $sourceQuery->where('created_at', '>=', $fromDate);
            $fromDateValue = $fromDate;
        }

        if ($mode === 'from_message') {
            if (! $fromMessageId) {
                throw ValidationException::withMessages([
                    'history_import_from_message_id' => ['history_import_from_message_id is required for from_message mode.'],
                ]);
            }

            $sourceMessage = Message::query()
                ->where('id', $fromMessageId)
                ->where('conversation_id', $sourceConversation->id)
                ->first();

            if (! $sourceMessage) {
                throw ValidationException::withMessages([
                    'history_import_from_message_id' => ['Selected message does not belong to source direct conversation.'],
                ]);
            }

            $sourceQuery->where('id', '>=', $fromMessageId);
            $fromMessageValue = $fromMessageId;
        }

        $sourceMessages = $sourceQuery->get();
        if ($sourceMessages->isEmpty()) {
            $this->logImport(
                actor: $actor,
                sourceConversation: $sourceConversation,
                targetConversation: $targetConversation,
                mode: $mode,
                importedCount: 0,
                importedAttachmentsCount: 0,
                fromMessageId: $fromMessageValue,
                fromDate: $fromDateValue,
            );

            return 0;
        }

        $importedCount = 0;
        $importedAttachmentsCount = 0;
        DB::transaction(function () use ($sourceMessages, $sourceConversation, $targetConversation, &$importedCount, &$importedAttachmentsCount): void {
            $latestImportedMessage = null;

            /** @var Message $sourceMessage */
            foreach ($sourceMessages as $sourceMessage) {
                $importedMessage = Message::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'conversation_id' => $targetConversation->id,
                    'sender_id' => $sourceMessage->sender_id,
                    'sender_type' => $sourceMessage->sender_type,
                    'external_id' => null,
                    'reply_to_message_id' => null,
                    'type' => $sourceMessage->type,
                    'body' => $sourceMessage->body,
                    'status' => 'sent',
                    'is_imported' => true,
                    'imported_from_conversation_id' => $sourceConversation->id,
                    'imported_from_message_id' => $sourceMessage->id,
                    'sent_at' => $sourceMessage->sent_at ?? $sourceMessage->created_at,
                    'delivered_at' => null,
                    'read_at' => null,
                    'edited_at' => null,
                    'deleted_at' => null,
                    // Keep imported payload intentionally safe and minimal.
                    'metadata' => ['imported' => true],
                ]);

                $importedCount++;
                $latestImportedMessage = $importedMessage;

                $importedAttachmentsCount += $this->copyAttachments($sourceMessage, $importedMessage, $targetConversation->id);
            }

            if ($latestImportedMessage) {
                $targetConversation->last_message_id = $latestImportedMessage->id;
                $targetConversation->last_message_at = $latestImportedMessage->created_at;
                $targetConversation->save();
            }
        });

        $this->logImport(
            actor: $actor,
            sourceConversation: $sourceConversation,
            targetConversation: $targetConversation,
            mode: $mode,
            importedCount: $importedCount,
            importedAttachmentsCount: $importedAttachmentsCount,
            fromMessageId: $fromMessageValue,
            fromDate: $fromDateValue,
        );

        return $importedCount;
    }

    private function copyAttachments(Message $sourceMessage, Message $targetMessage, int $targetConversationId): int
    {
        $sourceAttachments = MessageAttachment::query()
            ->where('message_id', $sourceMessage->id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'deleted')
            ->get();
        $copied = 0;

        /** @var MessageAttachment $attachment */
        foreach ($sourceAttachments as $attachment) {
            MessageAttachment::query()->create([
                'message_id' => $targetMessage->id,
                'conversation_id' => $targetConversationId,
                'uploaded_by' => $attachment->uploaded_by,
                'disk' => $attachment->disk,
                'path' => $attachment->path,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'checksum' => $attachment->checksum,
                'copied_from_attachment_id' => $attachment->id,
                'is_imported' => true,
                'status' => 'active',
                'metadata' => ['imported' => true],
            ]);
            $copied++;
        }

        return $copied;
    }

    private function logImport(
        User $actor,
        Conversation $sourceConversation,
        Conversation $targetConversation,
        string $mode,
        int $importedCount,
        int $importedAttachmentsCount,
        ?int $fromMessageId = null,
        ?string $fromDate = null
    ): void {
        $this->chatModerationService->logHistoryImported($actor, $sourceConversation, $targetConversation, [
            'source' => 'history_import',
            'import_mode' => $mode,
            'imported_messages_count' => $importedCount,
            'imported_attachments_count' => $importedAttachmentsCount,
            'from_message_id' => $fromMessageId,
            'from_at' => $fromDate,
            'created_group_from_direct' => true,
        ]);

        // WHY:
        // For message audit foundation we keep imported-message logging at batch
        // level to avoid heavy per-message writes during large imports.
        $this->chatModerationService->logMessageImported($actor, null, [
            'source' => 'history_import',
            'conversation_id' => $targetConversation->id,
            'conversation_type' => $targetConversation->type,
            'conversation_source' => $targetConversation->source,
            'source_conversation_id' => $sourceConversation->id,
            'history_import_mode' => $mode,
            'imported_messages_count' => $importedCount,
            'imported_attachments_count' => $importedAttachmentsCount,
            'was_imported' => true,
        ]);
    }
}
