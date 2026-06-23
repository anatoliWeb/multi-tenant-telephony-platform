<?php

namespace App\Http\Controllers\Api;

use App\Services\System\MonitoringHealthService;
use Illuminate\Http\JsonResponse;

class MonitoringHealthController extends BaseController
{
    public function __construct(
        private readonly MonitoringHealthService $healthService
    ) {
    }

    public function liveness(): JsonResponse
    {
        if (! config('monitoring.health.enabled', true)) {
            abort(404);
        }

        return response()->json($this->healthService->liveness());
    }

    public function readiness(): JsonResponse
    {
        if (! config('monitoring.health.protected_enabled', true)) {
            abort(404);
        }

        return $this->successResponse(
            $this->healthService->readiness(),
            'Monitoring health check completed'
        );
    }
}
