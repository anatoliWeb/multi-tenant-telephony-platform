<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\CreatePrivateGroupFromDirectRequest;
use App\Http\Requests\Api\CreateDirectConversationRequest;
use App\Http\Requests\Api\CreateGroupConversationRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Http\Resources\Chat\ChatParticipantResource;
use App\Http\Resources\Chat\ChatWebhookDeliverySummaryResource;
use App\Models\ChatWebhookDelivery;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\Chat\ChatConversationService;
use App\Services\Chat\ChatConversationQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatConversationController extends BaseController
{
    public function __construct(
        protected ChatConversationQueryService $queryService,
        protected ChatAccessService $accessService,
        protected ChatConversationService $conversationService,
    ) {
    }

    public function storeDirect(CreateDirectConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->conversationService->createDirectConversation(
            $user,
            (int) $request->validated('user_id')
        );

        $participant = $this->accessService->getParticipant($conversation, $user);
        $conversation->loadCount('participants');
        $conversation->setAttribute('unread_count', $this->queryService->unreadCountFor($user, $conversation));

        $payload = (new ChatConversationResource($conversation))
            ->forParticipant($participant)
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Conversation created', 201);
    }

    public function storeGroup(CreateGroupConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        $conversation = $this->conversationService->createGroupConversation(
            $user,
            $validated['participant_ids'],
            $validated
        );

        $participant = $this->accessService->getParticipant($conversation, $user);
        $conversation->loadCount('participants');
        $conversation->setAttribute('unread_count', $this->queryService->unreadCountFor($user, $conversation));

        $payload = (new ChatConversationResource($conversation))
            ->forParticipant($participant)
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Conversation created', 201);
    }

    public function createPrivateGroupFromDirect(
        CreatePrivateGroupFromDirectRequest $request,
        Conversation $conversation
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $groupConversation = $this->conversationService->createPrivateGroupFromDirect(
            $user,
            $conversation,
            $validated['participant_ids'],
            $validated
        );

        $participant = $this->accessService->getParticipant($groupConversation, $user);
        $groupConversation->loadCount('participants');
        $groupConversation->setAttribute('unread_count', $this->queryService->unreadCountFor($user, $groupConversation));

        $payload = (new ChatConversationResource($groupConversation))
            ->forParticipant($participant)
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $groupConversation))
            ->resolve();

        return $this->successResponse($payload, 'Private group conversation created', 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = [
            'type' => $request->query('type'),
            'visibility' => $request->query('visibility'),
            'status' => $request->query('status'),
            'source' => $request->query('source'),
            'unread' => filter_var($request->query('unread', false), FILTER_VALIDATE_BOOLEAN),
            'user' => $user,
        ];

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $paginator = $this->queryService
            ->visibleConversationsFor($user, $filters)
            ->withCount('participants')
            ->with([
                'participants' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $unreadCounts = $this->queryService->unreadCountsForConversations(
            $user,
            collect($paginator->items())->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $items = collect($paginator->items())->map(function (Conversation $conversation) use ($user, $unreadCounts): array {
            $participant = $conversation->participants->first();
            $conversation->setAttribute('unread_count', (int) ($unreadCounts[$conversation->id] ?? 0));

            return (new ChatConversationResource($conversation))
                ->forParticipant($participant)
                ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
                ->resolve();
        })->values()->all();

        return $this->paginatedSuccess($items, $paginator);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // WHY:
        // We return 404 for non-visible chats to avoid conversation enumeration.
        if (! $this->accessService->canViewConversation($user, $conversation)) {
            return $this->errorResponse('Conversation not found', null, 404);
        }

        $participant = $this->accessService->getParticipant($conversation, $user);
        $conversation->loadCount('participants');
        $conversation->setAttribute('unread_count', $this->queryService->unreadCountFor($user, $conversation));

        $payload = (new ChatConversationResource($conversation))
            ->forParticipant($participant)
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        if ($participant !== null) {
            $payload['current_user_participant'] = (new ChatParticipantResource($participant))
                ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
                ->resolve();
        }

        return $this->successResponse($payload, 'Request successful');
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->accessService->canViewConversation($user, $conversation)) {
            return $this->errorResponse('Conversation not found', null, 404);
        }

        $perPage = max(1, min((int) $request->query('per_page', 50), 100));
        $beforeId = $request->query('before_id');
        $canViewAdminMetadata = $this->queryService->applyAdminMetadataGate($user, $conversation);

        $query = $this->queryService
            ->visibleMessagesFor($user, $conversation)
            ->withCount('attachments');

        if ($canViewAdminMetadata) {
            $query->withCount('deviceReads as device_read_count')
                ->with(['deviceReads' => fn ($deviceReadsQuery) => $deviceReadsQuery
                    ->select(['id', 'message_id', 'user_id', 'device_type', 'read_at'])
                    ->orderBy('id')]);
        }

        if (is_numeric($beforeId)) {
            $query->where('id', '<', (int) $beforeId);
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($message) => (new ChatMessageResource($message))
            ->withAdminMetadata($canViewAdminMetadata)
            ->resolve())
            ->values()
            ->all();

        return $this->paginatedSuccess($items, $paginator);
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $participant = $this->conversationService->leaveConversation($user, $conversation);

        $payload = (new ChatParticipantResource($participant))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Conversation left');
    }

    public function close(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->conversationService->closeConversation($user, $conversation);
        $participant = $this->accessService->getParticipant($conversation, $user);
        $conversation->loadCount('participants');
        $conversation->setAttribute('unread_count', $this->queryService->unreadCountFor($user, $conversation));

        $payload = (new ChatConversationResource($conversation))
            ->forParticipant($participant)
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Conversation closed');
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->conversationService->archiveConversation($user, $conversation);
        $participant = $this->accessService->getParticipant($conversation, $user);
        $conversation->loadCount('participants');
        $conversation->setAttribute('unread_count', $this->queryService->unreadCountFor($user, $conversation));

        $payload = (new ChatConversationResource($conversation))
            ->forParticipant($participant)
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Conversation archived');
    }

    public function webhookDeliveries(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->accessService->canViewConversation($user, $conversation)) {
            return $this->errorResponse('Conversation not found', null, 404);
        }

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));

        $paginator = ChatWebhookDelivery::query()
            ->forCurrentTenant()
            ->where('conversation_id', $conversation->id)
            ->with(['endpoint:id,name,url'])
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (ChatWebhookDelivery $delivery) => (new ChatWebhookDeliverySummaryResource($delivery))->resolve())
            ->values()
            ->all();

        return $this->paginatedSuccess($items, $paginator);
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
