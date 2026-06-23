<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\AddChatParticipantRequest;
use App\Http\Requests\Api\BlockChatParticipantRequest;
use App\Http\Requests\Api\UpdateChatParticipantAccessRequest;
use App\Http\Requests\Api\UpdateChatParticipantCapabilitiesRequest;
use App\Http\Resources\Chat\ChatParticipantResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatConversationQueryService;
use App\Services\Chat\ChatConversationService;
use App\Services\Chat\ChatParticipantRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatConversationParticipantController extends BaseController
{
    public function __construct(
        protected ChatConversationService $conversationService,
        protected ChatConversationQueryService $queryService,
        protected ChatParticipantRestrictionService $restrictionService,
    ) {
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $participants = $this->conversationService->listParticipants($user, $conversation);
        $canViewAdminMetadata = $this->queryService->applyAdminMetadataGate($user, $conversation);

        $data = $participants
            ->map(fn ($participant) => (new ChatParticipantResource($participant))
                ->withAdminMetadata($canViewAdminMetadata)
                ->resolve())
            ->values()
            ->all();

        return $this->successResponse($data);
    }

    public function store(AddChatParticipantRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $capabilities = (array) ($validated['capabilities'] ?? []);

        $participant = $this->conversationService->addParticipant(
            $user,
            $conversation,
            (int) $validated['user_id'],
            [
                'role' => $validated['role'] ?? 'member',
                'can_invite' => $capabilities['can_invite'] ?? false,
                'can_remove' => $capabilities['can_remove'] ?? false,
                'can_send' => $capabilities['can_send'] ?? true,
                'can_attach' => $capabilities['can_attach'] ?? true,
                'can_manage' => $capabilities['can_manage'] ?? false,
                'can_moderate' => $capabilities['can_moderate'] ?? false,
            ]
        );

        $payload = (new ChatParticipantResource($participant))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Participant added', 201);
    }

    public function destroy(Request $request, Conversation $conversation, User $participantUser): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->conversationService->removeParticipant($user, $conversation, (int) $participantUser->id);

        return $this->successResponse([
            'conversation_id' => $conversation->id,
            'user_id' => $participantUser->id,
            'removed' => true,
        ], 'Participant removed');
    }

    public function updateAccess(
        UpdateChatParticipantAccessRequest $request,
        Conversation $conversation,
        User $participantUser
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $participant = $this->restrictionService->updateParticipantAccess(
            $user,
            $conversation,
            $participantUser,
            $request->validated()
        );

        $payload = (new ChatParticipantResource($participant))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Participant access updated');
    }

    public function block(
        BlockChatParticipantRequest $request,
        Conversation $conversation,
        User $participantUser
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $participant = $this->restrictionService->blockParticipant(
            $user,
            $conversation,
            $participantUser,
            $request->validated()
        );

        $payload = (new ChatParticipantResource($participant))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Participant blocked');
    }

    public function unblock(Request $request, Conversation $conversation, User $participantUser): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $participant = $this->restrictionService->unblockParticipant($user, $conversation, $participantUser);

        $payload = (new ChatParticipantResource($participant))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Participant unblocked');
    }

    public function updateCapabilities(
        UpdateChatParticipantCapabilitiesRequest $request,
        Conversation $conversation,
        User $participantUser
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $participant = $this->restrictionService->setParticipantCapabilities(
            $user,
            $conversation,
            $participantUser,
            $request->validated()
        );

        $payload = (new ChatParticipantResource($participant))
            ->withAdminMetadata($this->queryService->applyAdminMetadataGate($user, $conversation))
            ->resolve();

        return $this->successResponse($payload, 'Participant capabilities updated');
    }
}
