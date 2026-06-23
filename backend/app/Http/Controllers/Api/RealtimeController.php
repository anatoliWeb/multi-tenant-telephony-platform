<?php

namespace App\Http\Controllers\Api;

use App\Services\SocketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Realtime API Controller.
 *
 * WHY:
 * - Handles HTTP request/response layer only
 * - Delegates broadcasting logic to SocketService
 * - Keeps realtime smoke-test endpoint decoupled from concrete event classes
 */
class RealtimeController extends BaseController
{
    public function __construct(
        protected SocketService $socketService
    ) {
    }

    /**
     * Development-only realtime smoke-test trigger.
     *
     * WHY:
     * Provides one deterministic endpoint to validate end-to-end websocket
     * wiring without coupling controllers to broadcasting event classes.
     */
    public function notify(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        $this->socketService->broadcastSystemNotification(
            type: $payload['type'] ?? 'info',
            title: $payload['title'] ?? 'Realtime event',
            message: $payload['message'] ?? 'System notification delivered.',
        );

        return $this->successResponse([
            'dispatched' => true,
        ], 'Realtime notification dispatched');
    }
}
