<?php

namespace App\Http\Middleware;

use App\Models\ChatWebhookEndpoint;
use App\Models\User;
use App\Services\Chat\ExternalChatTokenService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExternalChatScopeMiddleware
{
    public function __construct(
        protected ExternalChatTokenService $tokenService
    ) {
    }

    /**
     * Authenticate external chat API request via Sanctum user or scoped endpoint token.
     *
     * Plain bearer token is hashed before lookup; raw token value is never persisted.
     * On successful token auth, request user resolver is swapped to endpoint creator.
     */
    public function handle(Request $request, Closure $next, string $requiredScope): Response
    {
        /** @var User|null $sanctumUser */
        $sanctumUser = auth('sanctum')->user();
        if ($sanctumUser instanceof User) {
            if (! $sanctumUser->hasAnyPermission(['chat.external_api.send', 'chat.external_api.manage', 'chat.admin.moderate'])) {
                throw new AuthorizationException('You are not allowed to send external chat messages.');
            }

            return $next($request);
        }

        $plainToken = (string) ($request->bearerToken() ?? '');
        if ($plainToken === '') {
            abort(401, 'Unauthenticated');
        }

        $tokenHash = $this->tokenService->hashToken($plainToken);

        /** @var ChatWebhookEndpoint|null $endpoint */
        $endpoint = ChatWebhookEndpoint::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('metadata->token_hash', $tokenHash)
            ->first();

        if (! $endpoint) {
            abort(401, 'Unauthenticated');
        }

        $metadata = is_array($endpoint->metadata) ? $endpoint->metadata : [];
        if (! $this->tokenService->tokenHasScope($metadata, $requiredScope)) {
            throw new AuthorizationException('Forbidden');
        }

        $creator = $endpoint->creator;
        if (! $creator instanceof User) {
            abort(401, 'Unauthenticated');
        }

        $request->setUserResolver(static fn (): User => $creator);
        $request->attributes->set('external_auth_mode', 'token');
        $request->attributes->set('external_token_endpoint_id', $endpoint->id);
        $request->attributes->set('external_token_scopes', data_get($metadata, 'token_scopes', []));

        $metadata['token_last_used_at'] = now()->toISOString();
        $endpoint->metadata = $metadata;
        $endpoint->last_used_at = now();
        $endpoint->save();

        return $next($request);
    }
}
