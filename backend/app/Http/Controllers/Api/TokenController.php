<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TokenResource;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Token Controller.
 *
 * WHY:
 * - Handles HTTP request/response layer only
 * - Delegates token lifecycle logic to TokenService
 * - Keeps API token response contract stable for frontend clients
 */
class TokenController extends BaseController
{
    /**
     * Inject TokenService.
     *
     * WHY:
     * TokenService owns token domain logic:
     * - list user tokens
     * - create new token
     * - normalize token abilities
     * - validate token ownership
     * - delete token
     */
    public function __construct(
        protected TokenService $tokenService
    ) {
    }

    /**
     * List all tokens for the authenticated user.
     *
     * WHY:
     * Controller only resolves current user and delegates token query
     * to TokenService.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        $tokens = $this->tokenService->listForUser($owner);

        return $this->successResponse(
            TokenResource::collection(collect($tokens))->resolve(),
            dt('notifications.success')
        );
    }

    /**
     * Create a new personal access token.
     *
     * WHY:
     * Request validation stays in controller.
     * Token creation and ability normalization stay in TokenService.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'exists:permissions,name'],
        ]);

        /** @var User $owner */
        $owner = $request->user();

        $payload = $this->tokenService->createForUser($owner, $validated);

        return $this->successResponse([
            'token' => $payload['token'],
            'access_token' => (new TokenResource($payload['access_token']))->resolve(),
        ], dt('notifications.created'), 201);
    }

    /**
     * Delete a token by ID.
     *
     * WHY:
     * TokenService performs strict ownership validation before deletion.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        $this->tokenService->deleteForUser($owner, $id);

        return $this->successResponse(null, dt('notifications.deleted'));
    }
}
