<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ActivityLogResource;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends BaseController
{
    public function __construct(
        protected ActivityService $activityService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:160'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject_type' => ['nullable', 'string', 'max:191'],
            'model' => ['nullable', 'string', 'max:191'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $activity = $this->activityService->listForApi($request->query());

        return $this->paginatedResponse(
            $activity,
            dt('notifications.success'),
            200,
            ActivityLogResource::class
        );
    }
}

