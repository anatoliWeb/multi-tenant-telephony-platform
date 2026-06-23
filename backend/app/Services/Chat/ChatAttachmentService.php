<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatAttachmentCreated;
use App\Events\Chat\ChatAttachmentDeleted;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentService
{
    public function __construct(
        protected ChatAccessService $accessService,
        protected ChatConversationQueryService $queryService,
        protected ChatModerationService $moderationService,
        protected ChatWebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    public function uploadAttachment(User $actor, Message $message, UploadedFile $file): MessageAttachment
    {
        $conversation = $message->conversation;
        if (! $conversation) {
            throw ValidationException::withMessages([
                'message' => ['Invalid message conversation.'],
            ]);
        }

        if (! $this->accessService->canAttachFile($actor, $conversation)) {
            throw new AuthorizationException('You are not allowed to upload attachments in this conversation.');
        }

        if (! $this->queryService->visibleMessagesFor($actor, $conversation)->whereKey($message->id)->exists()) {
            throw new AuthorizationException('You are not allowed to attach file to this message.');
        }

        if ($message->status === 'deleted' || $message->deleted_at !== null) {
            throw ValidationException::withMessages([
                'message' => ['Cannot attach file to deleted message.'],
            ]);
        }

        $disk = (string) config('chat.attachments.disk', 'local');
        $storage = Storage::disk($disk);

        $extension = $file->getClientOriginalExtension();
        $safeExtension = $extension !== '' ? '.'.$extension : '';
        $storedFileName = (string) Str::uuid().$safeExtension;
        $path = sprintf('chat/attachments/%d/%d/%s', $conversation->id, $message->id, $storedFileName);
        $storage->put($path, file_get_contents($file->getRealPath()));

        $scanEnabled = (bool) config('chat.attachments.virus_scan_enabled', false);
        $scanStatus = $scanEnabled ? 'pending' : 'skipped';

        $attachment = MessageAttachment::query()->create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'uploaded_by' => $actor->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'copied_from_attachment_id' => null,
            'is_imported' => false,
            'status' => 'active',
            'metadata' => [
                'preview' => $this->buildPreviewMetadata($file),
                'scan_status' => $scanStatus,
            ],
        ]);

        event(new ChatAttachmentCreated(
            conversationId: $conversation->id,
            payload: $this->buildAttachmentCreatedPayload($attachment)
        ));
        $this->webhookDeliveryService->queueEvent(
            'attachment.created',
            $this->buildAttachmentCreatedWebhookPayload($attachment)
        );

        $this->moderationService->logAttachmentUploaded($actor, $attachment, $this->buildAttachmentAuditMetadata($attachment, 'upload'));

        return $attachment;
    }

    public function downloadAttachment(User $actor, MessageAttachment $attachment): StreamedResponse
    {
        if (! $this->validateAttachmentAccess($actor, $attachment)) {
            throw new AuthorizationException('You are not allowed to download this attachment.');
        }

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            throw ValidationException::withMessages([
                'attachment' => ['Attachment file not found in storage.'],
            ]);
        }

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    public function markAttachmentDeleted(User $actor, MessageAttachment $attachment): MessageAttachment
    {
        if (! $this->validateAttachmentAccess($actor, $attachment, true)) {
            throw new AuthorizationException('You are not allowed to delete this attachment.');
        }

        if ($attachment->status === 'deleted') {
            return $attachment;
        }

        $attachment->status = 'deleted';
        $attachment->save();
        $attachment->delete();

        event(new ChatAttachmentDeleted(
            conversationId: $attachment->conversation_id,
            payload: $this->buildAttachmentDeletedPayload($attachment)
        ));

        $this->moderationService->logAttachmentDeleted($actor, $attachment, $this->buildAttachmentAuditMetadata($attachment, 'delete'));

        return $attachment;
    }

    public function markAttachmentsDeletedForMessage(Message $message, ?User $actor = null): void
    {
        MessageAttachment::query()
            ->where('message_id', $message->id)
            ->where('status', '!=', 'deleted')
            ->get()
            ->each(function (MessageAttachment $attachment) use ($actor): void {
                $attachment->status = 'deleted';
                $attachment->save();
                $attachment->delete();

                event(new ChatAttachmentDeleted(
                    conversationId: $attachment->conversation_id,
                    payload: $this->buildAttachmentDeletedPayload($attachment)
                ));

                if ($actor !== null) {
                    $this->moderationService->logAttachmentDeleted(
                        $actor,
                        $attachment,
                        $this->buildAttachmentAuditMetadata($attachment, 'message_delete')
                    );
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPreviewMetadata(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $category = str_contains($mime, '/')
            ? explode('/', $mime, 2)[0]
            : 'unknown';

        $preview = [
            'category' => $category,
            'extension' => $file->getClientOriginalExtension(),
        ];

        if (str_starts_with($mime, 'image/')) {
            $size = @getimagesize($file->getRealPath());
            if (is_array($size)) {
                $preview['width'] = $size[0] ?? null;
                $preview['height'] = $size[1] ?? null;
            }
        }

        return $preview;
    }

    public function validateAttachmentAccess(User $actor, MessageAttachment $attachment, bool $forDelete = false): bool
    {
        if (! in_array($attachment->status, ['active'], true)) {
            return false;
        }

        $message = $attachment->message;
        $conversation = $attachment->conversation;
        if (! $message || ! $conversation) {
            return false;
        }

        if ($message->status === 'deleted' || $message->deleted_at !== null) {
            return false;
        }

        if (! $this->queryService->visibleMessagesFor($actor, $conversation)->whereKey($message->id)->exists()) {
            return false;
        }

        if ($forDelete) {
            $isOwner = (int) $attachment->uploaded_by === (int) $actor->id;
            $isAdmin = $actor->hasAnyPermission(['chat.attachments.delete', 'chat.admin.moderate']);

            return $isOwner || $isAdmin;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttachmentCreatedPayload(MessageAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'message_id' => $attachment->message_id,
            'conversation_id' => $attachment->conversation_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'status' => $attachment->status,
            'is_imported' => (bool) $attachment->is_imported,
            'created_at' => $attachment->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttachmentDeletedPayload(MessageAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'message_id' => $attachment->message_id,
            'conversation_id' => $attachment->conversation_id,
            'status' => $attachment->status,
            'deleted_at' => $attachment->deleted_at?->toISOString(),
            'updated_at' => $attachment->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttachmentAuditMetadata(MessageAttachment $attachment, string $source): array
    {
        $mimeType = (string) ($attachment->mime_type ?? '');
        $category = str_contains($mimeType, '/')
            ? explode('/', $mimeType, 2)[0]
            : 'unknown';

        $extension = pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION);

        return [
            'source' => $source,
            'conversation_id' => $attachment->conversation_id,
            'message_id' => $attachment->message_id,
            'attachment_id' => $attachment->id,
            'uploaded_by_user_id' => $attachment->uploaded_by,
            'mime_type' => $mimeType,
            'file_type' => $category,
            'file_size' => (int) $attachment->size,
            'original_extension' => $extension !== '' ? strtolower((string) $extension) : null,
            'had_message' => $attachment->message_id !== null,
            'is_imported' => (bool) $attachment->is_imported,
            'status' => $attachment->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttachmentCreatedWebhookPayload(MessageAttachment $attachment): array
    {
        return [
            'event' => 'attachment.created',
            'attachment_id' => $attachment->id,
            'message_id' => $attachment->message_id,
            'conversation_id' => $attachment->conversation_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => (int) $attachment->size,
            'status' => $attachment->status,
            'is_imported' => (bool) $attachment->is_imported,
            'created_at' => $attachment->created_at?->toISOString(),
        ];
    }
}
