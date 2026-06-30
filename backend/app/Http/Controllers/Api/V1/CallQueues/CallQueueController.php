<?php

namespace App\Http\Controllers\Api\V1\CallQueues;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ListCallQueuesRequest;
use App\Http\Requests\Api\StoreCallQueueRequest;
use App\Http\Requests\Api\TestCallQueueRouteRequest;
use App\Http\Requests\Api\UpdateCallQueueRequest;
use App\Http\Resources\CallQueueResource;
use App\Models\CallQueue;
use App\Models\User;
use App\Services\CallQueues\CallQueueQueryService;
use App\Services\CallQueues\CallQueueService;
use Illuminate\Http\JsonResponse;

class CallQueueController extends BaseController
{
    public function __construct(
        private readonly CallQueueQueryService $queryService,
        private readonly CallQueueService $queueService,
    ) {
    }

    public function index(ListCallQueuesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CallQueue::class);

        $paginator = $this->queryService->paginate($request->validated());
        $items = CallQueueResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function show(CallQueue $callQueue): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('view', $callQueue);
        $callQueue->load(['members.extension', 'members.user']);
        $callQueue->loadCount([
            'members',
            'activeMembers',
            'members as paused_members_count' => fn ($builder) => $builder->where('is_paused', true),
        ]);

        return $this->successResponse((new CallQueueResource($callQueue))->resolve());
    }

    public function store(StoreCallQueueRequest $request): JsonResponse
    {
        $this->authorize('create', CallQueue::class);

        try {
            /** @var User $user */
            $user = $request->user();
            $queue = $this->queueService->create($request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new CallQueueResource($queue))->resolve(), 'Call queue created', 201);
    }

    public function update(UpdateCallQueueRequest $request, CallQueue $callQueue): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('update', $callQueue);

        try {
            /** @var User $user */
            $user = $request->user();
            $queue = $this->queueService->update($callQueue, $request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new CallQueueResource($queue))->resolve(), 'Call queue updated');
    }

    public function destroy(CallQueue $callQueue): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('delete', $callQueue);
        $this->queueService->delete($callQueue);

        return $this->successResponse(['deleted' => true], 'Call queue deleted');
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', CallQueue::class);

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
            'queues' => $options['queues']->toArray(),
            'ring_groups' => $options['ring_groups']->toArray(),
            'strategies' => $options['strategies'],
            'statuses' => $options['statuses'],
            'overflow_destinations' => $options['overflow_destinations'],
        ]);
    }

    public function testRoute(TestCallQueueRouteRequest $request, CallQueue $callQueue): JsonResponse
    {
        if (! $callQueue->isInCurrentTenant()) {
            abort(404, 'Call queue not found.');
        }

        $this->authorize('testRoute', $callQueue);

        return $this->successResponse($this->queueService->testRoute($callQueue));
    }
}
