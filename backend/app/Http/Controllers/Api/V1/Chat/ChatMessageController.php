<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\SendExternalChatMessageRequest;
use App\Http\Requests\Api\SearchChatMessagesRequest;
use App\Http\Requests\Api\SendChatMessageRequest;
use App\Http\Requests\Api\UpdateChatMessageRequest;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatConversationQueryService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ExternalChatMessageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ChatMessageController extends BaseController
{
    public function __construct(
        protected ChatMessageService $messageService,
        protected ChatConversationQueryService $queryService,
        protected ExternalChatMessageService $externalChatMessageService,
    ) {
    }

    public function store(SendChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $message = $this->messageService->sendMessage($user, $conversation, $request->validated());

        $payload = (new ChatMessageResource($message))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Message sent', 201);
    }

    public function update(UpdateChatMessageRequest $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $this->messageService->editMessage($user, $message, $request->validated());

        $conversation = $updated->conversation;
        $payload = (new ChatMessageResource($updated))
            ->withAdminMetadata($conversation ? $this->queryService->applyAdminMetadataGate($user, $conversation) : false)
            ->resolve();

        return $this->successResponse($payload, 'Message updated');
    }

    public function destroy(Message $message): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $deleted = $this->messageService->deleteMessage($user, $message);

        return $this->successResponse([
            'id' => $deleted->id,
            'conversation_id' => $deleted->conversation_id,
            'status' => $deleted->status,
            'deleted_at' => $deleted->deleted_at?->toISOString(),
        ], 'Message deleted');
    }

    public function search(SearchChatMessagesRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $perPage = max(1, min((int) ($validated['per_page'] ?? 20), 100));

        $paginator = $this->queryService
            ->searchVisibleMessages($user, $conversation, $validated)
            ->withCount('attachments')
            ->orderByDesc('id')
            ->paginate($perPage);

        $canViewAdminMetadata = $this->queryService->applyAdminMetadataGate($user, $conversation);
        $items = collect($paginator->items())->map(fn ($message) => (new ChatMessageResource($message))
            ->withAdminMetadata($canViewAdminMetadata)
            ->resolve())
            ->values()
            ->all();

        return $this->paginatedSuccess($items, $paginator);
    }

    public function storeExternal(SendExternalChatMessageRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $externalAuthMode = (string) $request->attributes->get('external_auth_mode', '');
        $result = $this->externalChatMessageService->sendExternalMessage(
            $user,
            $request->validated(),
            'external_api',
            'external_in',
            $externalAuthMode !== 'token'
        );
        $message = $result['message'];
        $conversation = $message->conversation;

        $payload = (new ChatMessageResource($message))
            ->withAdminMetadata($conversation ? $this->queryService->applyAdminMetadataGate($user, $conversation) : false)
            ->resolve();

        return response()->json([
            'success' => true,
            'message' => ($result['idempotent'] ?? false) ? 'External message already exists' : 'External message created',
            'data' => $payload,
            'meta' => [
                'idempotent' => (bool) ($result['idempotent'] ?? false),
            ],
        ], ($result['idempotent'] ?? false) ? 200 : 201);
    }

    /**
     * @param array<int, mixed> $items
     */
    private function paginatedSuccess(array $items, LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Request successful',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
