<?php

namespace App\Http\Controllers\Api\V1\RingGroups;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\StoreRingGroupMemberRequest;
use App\Http\Requests\Api\UpdateRingGroupMemberRequest;
use App\Http\Resources\RingGroupMemberResource;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Services\RingGroups\RingGroupService;
use Illuminate\Http\JsonResponse;

class RingGroupMemberController extends BaseController
{
    public function __construct(
        private readonly RingGroupService $ringGroupService,
    ) {
    }

    public function index(RingGroup $ringGroup): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant()) {
            abort(404, 'Ring group not found.');
        }

        $this->authorize('view', $ringGroup);
        $ringGroup->load(['members.extension', 'members.user']);

        return $this->successResponse(RingGroupMemberResource::collection($ringGroup->members)->resolve());
    }

    public function store(StoreRingGroupMemberRequest $request, RingGroup $ringGroup): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant()) {
            abort(404, 'Ring group not found.');
        }

        $this->authorize('manageMembers', $ringGroup);

        try {
            $member = $this->ringGroupService->createMember($ringGroup, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new RingGroupMemberResource($member))->resolve(), 'Ring group member created', 201);
    }

    public function update(UpdateRingGroupMemberRequest $request, RingGroup $ringGroup, RingGroupMember $member): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant() || ! $member->isInCurrentTenant()) {
            abort(404, 'Ring group member not found.');
        }

        $this->authorize('manageMembers', $ringGroup);

        try {
            $member = $this->ringGroupService->updateMember($ringGroup, $member, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new RingGroupMemberResource($member))->resolve(), 'Ring group member updated');
    }

    public function destroy(RingGroup $ringGroup, RingGroupMember $member): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant() || ! $member->isInCurrentTenant()) {
            abort(404, 'Ring group member not found.');
        }

        $this->authorize('manageMembers', $ringGroup);
        $this->ringGroupService->deleteMember($ringGroup, $member);

        return $this->successResponse(['deleted' => true], 'Ring group member deleted');
    }
}
