<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ChatTypingRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatTypingService;
use Illuminate\Http\JsonResponse;

class ChatTypingController extends BaseController
{
    public function __construct(
        protected ChatTypingService $typingService,
    ) {
    }

    public function start(ChatTypingRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->typingService->startTyping($user, $conversation, $request->validated());

        return $this->successResponse([
            'status' => 'ok',
        ], 'Typing started');
    }

    public function stop(ChatTypingRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->typingService->stopTyping($user, $conversation, $request->validated());

        return $this->successResponse([
            'status' => 'ok',
        ], 'Typing stopped');
    }
}

