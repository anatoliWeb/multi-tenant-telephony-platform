<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\UploadChatAttachmentRequest;
use App\Http\Resources\Chat\ChatAttachmentResource;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends BaseController
{
    public function __construct(
        protected ChatAttachmentService $attachmentService
    ) {
    }

    public function store(UploadChatAttachmentRequest $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attachment = $this->attachmentService->uploadAttachment($user, $message, $request->file('file'));

        return $this->successResponse(
            (new ChatAttachmentResource($attachment))->resolve(),
            'Attachment uploaded',
            201
        );
    }

    public function download(MessageAttachment $attachment): StreamedResponse
    {
        /** @var User $user */
        $user = request()->user();

        return $this->attachmentService->downloadAttachment($user, $attachment);
    }

    public function destroy(MessageAttachment $attachment): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $deleted = $this->attachmentService->markAttachmentDeleted($user, $attachment);

        return $this->successResponse([
            'id' => $deleted->id,
            'message_id' => $deleted->message_id,
            'status' => $deleted->status,
            'deleted' => true,
        ], 'Attachment deleted');
    }
}

