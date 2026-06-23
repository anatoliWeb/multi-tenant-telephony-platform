<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\MarkConversationReadRequest;
use App\Http\Requests\Api\MarkMessageReadRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chat\ChatReadStateService;
use Illuminate\Http\JsonResponse;

class ChatReadStateController extends BaseController
{
    public function __construct(
        protected ChatReadStateService $readStateService
    ) {
    }

    public function markMessageRead(MarkMessageReadRequest $request, Message $message): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthorized', null, 401);
        }

        $device = $this->readStateService->resolveOwnedDevice($user, (string) $request->validated('device_key'));
        if (! $device) {
            return $this->errorResponse('Chat device not found for current user', [
                'device_key' => ['Unknown device key for current user.'],
            ], 422);
        }

        if (! $this->readStateService->canMarkMessageRead($user, $message)) {
            return $this->errorResponse('Message not found', null, 404);
        }

        $this->readStateService->markMessageReadFromDevice($user, $message, $device);

        return $this->successResponse([
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'device_id' => $device->id,
            'read' => true,
        ], 'Message marked as read');
    }

    public function markConversationRead(MarkConversationReadRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthorized', null, 401);
        }

        $device = $this->readStateService->resolveOwnedDevice($user, (string) $request->validated('device_key'));
        if (! $device) {
            return $this->errorResponse('Chat device not found for current user', [
                'device_key' => ['Unknown device key for current user.'],
            ], 422);
        }

        if (! $this->readStateService->canMarkConversationRead($user, $conversation)) {
            return $this->errorResponse('Conversation not found', null, 404);
        }

        $this->readStateService->markConversationReadFromDevice(
            $user,
            $conversation,
            $device,
            $request->validated('until_message_id')
        );

        return $this->successResponse([
            'conversation_id' => $conversation->id,
            'device_id' => $device->id,
            'read' => true,
        ], 'Conversation marked as read');
    }
}

