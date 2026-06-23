<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Base controller for all API endpoints.
 *
 * Centralizes response formatting to guarantee a single, predictable
 * contract for frontend consumers (Angular/Vue) and to avoid duplicated
 * JSON-building logic in feature controllers.
 */
class BaseController extends Controller
{
    /**
     * Build a standardized successful API response.
     *
     * @param mixed $data      Payload returned to client.
     * @param string $message  Human-readable operation result.
     * @param int $statusCode  HTTP status code.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Request successful',
        int $statusCode = 200
    ): JsonResponse {
        return ApiResponse::success($data, $message, $statusCode);
    }

    /**
     * Build a standardized error API response.
     *
     * @param string $message  Human-readable error message.
     * @param mixed $errors    Optional structured error details.
     * @param int $statusCode  HTTP status code.
     */
    protected function errorResponse(
        string $message = 'Request failed',
        mixed $errors = null,
        int $statusCode = 400
    ): JsonResponse {
        return ApiResponse::error($message, $errors, $statusCode);
    }

    /**
     * Build a standardized paginated API response.
     *
     * Uses Laravel paginator metadata so clients can render paging controls
     * consistently without endpoint-specific parsing rules.
     *
     * @param LengthAwarePaginator $paginator Data source with pagination.
     * @param string $message                 Human-readable operation result.
     * @param int $statusCode                 HTTP status code.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Data fetched',
        int $statusCode = 200,
        ?string $resourceClass = null
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $message, $statusCode, $resourceClass);
    }
}
