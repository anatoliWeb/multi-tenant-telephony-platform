<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ChatPresenceLeaveRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatPresenceService;
use Illuminate\Http\JsonResponse;

class ChatPresenceController extends BaseController
{
    public function __construct(
        protected ChatPresenceService $presenceService,
    ) {
    }

    public function leave(ChatPresenceLeaveRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->presenceService->markUserPresenceLeft(
            $user,
            $conversation,
            $request->validated('device_key')
        );

        return $this->successResponse([
            'status' => 'ok',
        ], 'Presence left');
    }
}

