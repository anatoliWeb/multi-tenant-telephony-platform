<?php

namespace App\Http\Controllers\Api\V1\RingGroups;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ListRingGroupsRequest;
use App\Http\Requests\Api\StoreRingGroupRequest;
use App\Http\Requests\Api\TestRingGroupRouteRequest;
use App\Http\Requests\Api\UpdateRingGroupRequest;
use App\Http\Resources\RingGroupMemberResource;
use App\Http\Resources\RingGroupResource;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\RingGroups\RingGroupQueryService;
use App\Services\RingGroups\RingGroupService;
use Illuminate\Http\JsonResponse;

class RingGroupController extends BaseController
{
    public function __construct(
        private readonly RingGroupQueryService $queryService,
        private readonly RingGroupService $ringGroupService,
    ) {
    }

    public function index(ListRingGroupsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', RingGroup::class);

        $paginator = $this->queryService->paginate($request->validated());
        $items = RingGroupResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function show(RingGroup $ringGroup): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant()) {
            abort(404, 'Ring group not found.');
        }

        $this->authorize('view', $ringGroup);

        $ringGroup->load(['members.extension', 'members.user']);

        return $this->successResponse((new RingGroupResource($ringGroup))->resolve());
    }

    public function store(StoreRingGroupRequest $request): JsonResponse
    {
        $this->authorize('create', RingGroup::class);

        try {
            /** @var User $user */
            $user = $request->user();
            $ringGroup = $this->ringGroupService->create($request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new RingGroupResource($ringGroup))->resolve(), 'Ring group created', 201);
    }

    public function update(UpdateRingGroupRequest $request, RingGroup $ringGroup): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant()) {
            abort(404, 'Ring group not found.');
        }

        $this->authorize('update', $ringGroup);

        try {
            /** @var User $user */
            $user = $request->user();
            $ringGroup = $this->ringGroupService->update($ringGroup, $request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new RingGroupResource($ringGroup))->resolve(), 'Ring group updated');
    }

    public function destroy(RingGroup $ringGroup): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant()) {
            abort(404, 'Ring group not found.');
        }

        $this->authorize('delete', $ringGroup);
        $this->ringGroupService->delete($ringGroup);

        return $this->successResponse(['deleted' => true], 'Ring group deleted');
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', RingGroup::class);

        $options = $this->queryService->options();

        return $this->successResponse([
            'extensions' => $options['extensions']->map(fn ($extension): array => [
                'id' => $extension->id,
                'number' => $extension->number,
                'label' => $extension->label,
                'status' => $extension->status?->value ?? $extension->status,
            ])->values()->all(),
            'users' => $options['users']->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all(),
            'strategies' => $options['strategies'],
            'statuses' => $options['statuses'],
        ]);
    }

    public function testRoute(TestRingGroupRouteRequest $request, RingGroup $ringGroup): JsonResponse
    {
        if (! $ringGroup->isInCurrentTenant()) {
            abort(404, 'Ring group not found.');
        }

        $this->authorize('testRoute', $ringGroup);

        return $this->successResponse($this->ringGroupService->testRoute($ringGroup));
    }
}
