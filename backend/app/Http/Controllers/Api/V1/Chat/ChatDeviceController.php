<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\RegisterChatDeviceRequest;
use App\Services\Chat\ChatReadStateService;
use Illuminate\Http\JsonResponse;

class ChatDeviceController extends BaseController
{
    public function __construct(
        protected ChatReadStateService $readStateService
    ) {
    }

    public function upsert(RegisterChatDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthorized', null, 401);
        }

        $device = $this->readStateService->registerOrUpdateDevice($user, $request->validated());

        return $this->successResponse([
            'id' => $device->id,
            'uuid' => $device->uuid,
            'device_key' => $device->device_key,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type,
            'platform' => $device->platform,
            'browser' => $device->browser,
            'app_version' => $device->app_version,
            'is_active' => (bool) $device->is_active,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ], 'Device registered');
    }
}

