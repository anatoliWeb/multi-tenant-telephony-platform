<?php

namespace App\Http\Controllers\Api\V1\Ivr;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\StoreIvrOptionRequest;
use App\Http\Requests\Api\UpdateIvrOptionRequest;
use App\Http\Resources\IvrOptionResource;
use App\Models\IvrMenu;
use App\Models\IvrOption;
use App\Services\Ivr\IvrMenuService;
use Illuminate\Http\JsonResponse;

class IvrOptionController extends BaseController
{
    public function __construct(
        private readonly IvrMenuService $menuService,
    ) {
    }

    public function index(IvrMenu $ivrMenu): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('view', $ivrMenu);
        $ivrMenu->load(['options']);

        return $this->successResponse(IvrOptionResource::collection($ivrMenu->options)->resolve());
    }

    public function store(StoreIvrOptionRequest $request, IvrMenu $ivrMenu): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('manageOptions', $ivrMenu);

        try {
            $option = $this->menuService->createOption($ivrMenu, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new IvrOptionResource($option))->resolve(), 'IVR option created', 201);
    }

    public function update(UpdateIvrOptionRequest $request, IvrMenu $ivrMenu, IvrOption $option): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('manageOptions', $ivrMenu);

        if ((string) $option->tenant_id !== (string) $ivrMenu->tenant_id || (string) $option->ivr_menu_id !== (string) $ivrMenu->getKey()) {
            abort(404, 'IVR option not found.');
        }

        try {
            $option = $this->menuService->updateOption($ivrMenu, $option, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new IvrOptionResource($option))->resolve(), 'IVR option updated');
    }

    public function destroy(IvrMenu $ivrMenu, IvrOption $option): JsonResponse
    {
        if (! $ivrMenu->isInCurrentTenant()) {
            abort(404, 'IVR menu not found.');
        }

        $this->authorize('manageOptions', $ivrMenu);

        if ((string) $option->tenant_id !== (string) $ivrMenu->tenant_id || (string) $option->ivr_menu_id !== (string) $ivrMenu->getKey()) {
            abort(404, 'IVR option not found.');
        }

        $this->menuService->deleteOption($ivrMenu, $option);

        return $this->successResponse(['deleted' => true], 'IVR option deleted');
    }
}
