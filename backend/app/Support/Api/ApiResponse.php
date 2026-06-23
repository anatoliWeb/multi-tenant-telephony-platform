<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared API response contract builder.
 *
 * WHY SHARED CONTRACTS MATTER:
 * A centralized response contract ensures every endpoint speaks the same
 * language, regardless of controller, feature module, or transport layer.
 *
 * WHY FRONTENDS NEED PREDICTABLE RESPONSES:
 * Angular, Vue, and future mobile clients should parse one stable envelope
 * (`success`, `message`, `data`, `errors`, `meta`) instead of endpoint-specific
 * formats. This reduces client-side branching and integration risk.
 *
 * WHY CENTRALIZED FORMATTING IS CRITICAL:
 * Keeping response assembly in one place prevents drift, duplicated arrays,
 * and subtle inconsistencies between controllers and resources.
 *
 * HOW THIS PREPARES SCALING:
 * This contract can be reused for HTTP APIs today and later mirrored by
 * WebSocket events, notification payloads, and extracted services/microservices.
 * It creates a portable boundary for system-to-system communication.
 */
class ApiResponse
{
    /**
     * Build a standardized success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @param array<string, mixed>|null $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'Request successful',
        int $statusCode = 200,
        ?array $meta = null
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Build a standardized error response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $statusCode
     * @param array<string, mixed>|null $meta
     */
    public static function error(
        string $message = 'Request failed',
        mixed $errors = null,
        int $statusCode = 400,
        ?array $meta = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Build a standardized paginated success response.
     *
     * @param LengthAwarePaginator $paginator
     * @param string $message
     * @param int $statusCode
     * @param class-string<JsonResource>|null $resourceClass
     * @param array<string, mixed> $metaOverrides
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Data fetched',
        int $statusCode = 200,
        ?string $resourceClass = null,
        array $metaOverrides = []
    ): JsonResponse {
        $items = $paginator->items();

        if ($resourceClass !== null && is_subclass_of($resourceClass, JsonResource::class)) {
            $items = $resourceClass::collection(collect($items))->resolve();
        }

        $meta = array_merge([
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ], $metaOverrides);

        return self::success($items, $message, $statusCode, $meta);
    }
}
