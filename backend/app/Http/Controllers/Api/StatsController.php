<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\StatsResource;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
class StatsController extends BaseController
{
    /**
     * Stats service instance.
     *
     * Handles business logic for application statistics.
     */
    protected StatsService $statsService;

    /**
     * Create a new controller instance.
     *
     * @param StatsService $statsService
     */
    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }


    /**
     * Display basic application statistics.
     *
     * Returns mocked statistical data used
     * for dashboard representation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $stats = $this->statsService->getStats();

            return $this->successResponse(
                (new StatsResource($stats))->resolve(),
                dt('notifications.success')
            );

        } catch (\Throwable $e) {

            // IMPORTANT:
            // Never expose internal errors to client
            // but always log them
            Log::error('Stats fetch failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(dt('notifications.error'), null, 500);
        }
    }
}
