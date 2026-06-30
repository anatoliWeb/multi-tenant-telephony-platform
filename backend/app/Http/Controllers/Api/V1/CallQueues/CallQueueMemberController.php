<?php

namespace App\Http\Controllers\Api\V1\CallQueues;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\PauseCallQueueMemberRequest;
use App\Http\Requests\Api\ResumeCallQueueMemberRequest;
use App\Http\Requests\Api\StoreCallQueueMemberRequest;
use App\Http\Requests\Api\UpdateCallQueueMemberRequest;
use App\Http\Resources\CallQueueMemberResource;
use App\Models\CallQueue;
use App\Models\CallQueueMember;
use App\Models\User;
use App\Services\CallQueues\CallQueueService;
use Illuminate\Http\JsonResponse;

class CallQueueMemberController extends BaseController
{
    public function __construct(
        private readonly CallQueueService $queueService,
    ) {
    }

    public function index(CallQueue $callQueue): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('view', $callQueue);
        $callQueue->load(['members.extension', 'members.user']);

        return $this->successResponse(CallQueueMemberResource::collection($callQueue->members)->resolve());
    }

    public function store(StoreCallQueueMemberRequest $request, CallQueue $callQueue): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('manageMembers', $callQueue);

        try {
            $member = $this->queueService->createMember($callQueue, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new CallQueueMemberResource($member))->resolve(), 'Call queue member created', 201);
    }

    public function update(UpdateCallQueueMemberRequest $request, CallQueue $callQueue, CallQueueMember $member): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('manageMembers', $callQueue);

        if ((string) $member->tenant_id !== (string) $callQueue->tenant_id || (string) $member->call_queue_id !== (string) $callQueue->getKey()) {
            abort(404, 'Call queue member not found.');
        }

        try {
            $member = $this->queueService->updateMember($callQueue, $member, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new CallQueueMemberResource($member))->resolve(), 'Call queue member updated');
    }

    public function destroy(CallQueue $callQueue, CallQueueMember $member): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('manageMembers', $callQueue);

        if ((string) $member->tenant_id !== (string) $callQueue->tenant_id || (string) $member->call_queue_id !== (string) $callQueue->getKey()) {
            abort(404, 'Call queue member not found.');
        }

        $this->queueService->deleteMember($callQueue, $member);

        return $this->successResponse(['deleted' => true], 'Call queue member deleted');
    }

    public function pause(PauseCallQueueMemberRequest $request, CallQueue $callQueue, CallQueueMember $member): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('pauseMembers', [$callQueue, $member]);

        if ((string) $member->tenant_id !== (string) $callQueue->tenant_id || (string) $member->call_queue_id !== (string) $callQueue->getKey()) {
            abort(404, 'Call queue member not found.');
        }

        try {
            /** @var User $user */
            $user = $request->user();
            $member = $this->queueService->pauseMember($callQueue, $member, $user, (string) $request->validated('reason'));
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new CallQueueMemberResource($member))->resolve(), 'Call queue member paused');
    }

    public function resume(ResumeCallQueueMemberRequest $request, CallQueue $callQueue, CallQueueMember $member): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('pauseMembers', [$callQueue, $member]);

        if ((string) $member->tenant_id !== (string) $callQueue->tenant_id || (string) $member->call_queue_id !== (string) $callQueue->getKey()) {
            abort(404, 'Call queue member not found.');
        }

        try {
            /** @var User $user */
            $user = $request->user();
            $member = $this->queueService->resumeMember($callQueue, $member, $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new CallQueueMemberResource($member))->resolve(), 'Call queue member resumed');
    }
}
