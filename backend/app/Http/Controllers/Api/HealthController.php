<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * Lightweight infrastructure health endpoint.
 *
 * Intended for container checks and basic availability probing.
 * No domain/business logic should be placed here.
 */
class HealthController extends BaseController
{
    /**
     * Return API health status in standardized response format.
     */
    public function show(): JsonResponse
    {
        return $this->successResponse(
            ['status' => 'ok'],
            'Service is healthy'
        );
    }
}
