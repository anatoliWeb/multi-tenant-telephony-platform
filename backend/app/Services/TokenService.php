<?php

namespace App\Services;

use App\Events\Auth\TokenCreated;
use App\Events\Auth\TokenRevoked;
use App\DTO\TokenPayloadDTO;
use App\Models\User;
use App\Observers\PersonalAccessTokenObserver;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    /**
     * Get personal access tokens for user.
     *
     * WHY:
     * Tokens are always scoped to authenticated user
     * to prevent accidental data leakage across accounts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $owner): array
    {
        return $owner
            ->tokens()
            ->select(['id', 'name', 'abilities', 'created_at'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => $this->transformToken($token, $owner))
            ->values()
            ->all();
    }

    /**
     * Create personal access token for user.
     *
     * WHY:
     * Token secret is returned only once.
     * Plain text token is never stored in database.
     *
     * @param array{name: string, scopes?: array<int, string>|null} $data
     *
     * @return array<string, mixed>
     */
    public function createForUser(User $owner, array $data): array
    {
        $abilities = $this->normalizeAbilities($data['scopes'] ?? null);

        PersonalAccessTokenObserver::suppressNextCreated();
        $token = $owner->createToken($data['name'], $abilities);

        event(new TokenCreated(
            tokenId: $token->accessToken->id,
            tokenName: $token->accessToken->name,
            tokenableId: $owner->id,
            actorId: auth()->id(),
            abilities: $abilities,
            occurredAt: now()->toIso8601String(),
        ));

        return [
            'token' => $token->plainTextToken,
            'access_token' => $this->transformNewAccessToken($token, $owner, $abilities),
        ];
    }

    /**
     * Delete personal access token owned by user.
     *
     * WHY:
     * Sanctum tokens are stored globally,
     * so ownership must be checked manually.
     */
    public function deleteForUser(User $owner, int $tokenId): void
    {
        $token = PersonalAccessToken::query()->findOrFail($tokenId);

        $this->assertOwnership($token, $owner);

        event(new TokenRevoked(
            tokenId: $token->id,
            tokenName: $token->name,
            tokenableId: (int) $token->tokenable_id,
            actorId: auth()->id(),
            revokeReason: 'user_requested',
            occurredAt: now()->toIso8601String(),
        ));

        PersonalAccessTokenObserver::suppressNextDeleted();
        $token->delete();
    }

    /**
     * Normalize frontend scopes to Sanctum abilities.
     *
     * WHY:
     * Empty scopes means full access token in current API contract.
     *
     * @param array<int, string>|null $scopes
     *
     * @return array<int, string>
     */
    protected function normalizeAbilities(?array $scopes): array
    {
        return !empty($scopes)
            ? array_values($scopes)
            : ['*'];
    }

    /**
     * Ensure token belongs to current user.
     *
     * WHY:
     * Prevents deleting tokens owned by other users or other tokenable models.
     */
    protected function assertOwnership(PersonalAccessToken $token, User $owner): void
    {
        if (
            (int) $token->tokenable_id !== (int) $owner->id ||
            $token->tokenable_type !== $owner::class
        ) {
            abort(403);
        }
    }

    /**
     * Transform existing token to current API response shape.
     *
     * WHY:
     * Keeps frontend contract stable after moving logic out of controller.
     *
     * @return array<string, mixed>
     */
    protected function transformToken(PersonalAccessToken $token, User $owner): array
    {
        return (new TokenPayloadDTO(
            id: $token->id,
            name: $token->name,
            abilities: $token->abilities,
            createdAt: $token->created_at,
            owner: [
                'id' => $owner->id,
                'name' => $owner->name,
            ],
        ))->toArray();
    }

    /**
     * Transform newly created token to current API response shape.
     *
     * WHY:
     * Newly created token uses Laravel Sanctum NewAccessToken wrapper.
     *
     * @param array<int, string> $abilities
     *
     * @return array<string, mixed>
     */
    protected function transformNewAccessToken(NewAccessToken $token, User $owner, array $abilities): array
    {
        return (new TokenPayloadDTO(
            id: $token->accessToken->id,
            name: $token->accessToken->name,
            abilities: $abilities,
            createdAt: $token->accessToken->created_at,
            owner: [
                'id' => $owner->id,
                'name' => $owner->name,
            ],
        ))->toArray();
    }
}
