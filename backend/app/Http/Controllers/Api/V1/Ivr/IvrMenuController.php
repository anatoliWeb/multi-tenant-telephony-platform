<?php

namespace App\Http\Controllers\Api\V1\Ivr;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ListIvrMenusRequest;
use App\Http\Requests\Api\StoreIvrMenuRequest;
use App\Http\Requests\Api\TestIvrMenuRouteRequest;
use App\Http\Requests\Api\UpdateIvrMenuRequest;
use App\Http\Resources\IvrMenuResource;
use App\Models\IvrMenu;
use App\Models\User;
use App\Services\Ivr\IvrMenuQueryService;
use App\Services\Ivr\IvrMenuService;
use Illuminate\Http\JsonResponse;

class IvrMenuController extends BaseController
{
    public function __construct(
        private readonly IvrMenuQueryService $queryService,
        private readonly IvrMenuService $menuService,
    ) {
    }

    public function index(ListIvrMenusRequest $request): JsonResponse
    {
        $this->authorize('viewAny', IvrMenu::class);

        $paginator = $this->queryService->paginate($request->validated());
        $items = IvrMenuResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function show(IvrMenu $ivrMenu): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('view', $ivrMenu);
        $ivrMenu->load(['options']);
        $ivrMenu->loadCount(['options', 'activeOptions']);

        return $this->successResponse((new IvrMenuResource($ivrMenu))->resolve());
    }

    public function store(StoreIvrMenuRequest $request): JsonResponse
    {
        $this->authorize('create', IvrMenu::class);

        try {
            /** @var User $user */
            $user = $request->user();
            $menu = $this->menuService->create($request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new IvrMenuResource($menu))->resolve(), 'IVR menu created', 201);
    }

    public function update(UpdateIvrMenuRequest $request, IvrMenu $ivrMenu): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('update', $ivrMenu);

        try {
            /** @var User $user */
            $user = $request->user();
            $menu = $this->menuService->update($ivrMenu, $request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new IvrMenuResource($menu))->resolve(), 'IVR menu updated');
    }

    public function destroy(IvrMenu $ivrMenu): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('delete', $ivrMenu);
        $this->menuService->delete($ivrMenu);

        return $this->successResponse(['deleted' => true], 'IVR menu deleted');
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', IvrMenu::class);

        $options = $this->queryService->options();

        return $this->successResponse([
            'extensions' => $options['extensions']->map(fn ($extension): array => [
                'id' => $extension->id,
                'number' => $extension->number,
                'label' => $extension->label,
                'status' => $extension->status?->value ?? $extension->status,
            ])->values()->all(),
            'ring_groups' => $options['ring_groups']->toArray(),
            'call_queues' => $options['call_queues']->toArray(),
            'ivr_menus' => $options['ivr_menus']->toArray(),
            'destination_types' => $options['destination_types'],
            'actions' => $options['actions'],
            'statuses' => $options['statuses'],
            'digits' => $options['digits'],
        ]);
    }

    public function testRoute(TestIvrMenuRouteRequest $request, IvrMenu $ivrMenu): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('testRoute', $ivrMenu);

        return $this->successResponse($this->menuService->testRoute($ivrMenu, $request->validated()));
    }
}
