<?php

namespace App\Services\Chat;

use Illuminate\Support\Str;
use InvalidArgumentException;

class ExternalChatTokenService
{
    public function generatePlainToken(): string
    {
        return $this->tokenPrefix().Str::random(48);
    }

    public function hashToken(string $plainToken): string
    {
        $algo = (string) config('chat.external_api.token_hash_algo', 'sha256');

        return hash($algo, $plainToken);
    }

    public function tokenPrefix(): string
    {
        return (string) config('chat.external_api.token_prefix', 'chat_ext_');
    }

    public function verifyToken(string $plainToken, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hashToken($plainToken));
    }

    /**
     * @return array<int, string>
     */
    public function allowedScopes(): array
    {
        $scopes = config('chat.external_api.scopes.allowed', []);
        if (! is_array($scopes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($scope): string => trim((string) $scope),
            $scopes
        ))));
    }

    /**
     * @param array<int, mixed> $scopes
     * @return array<int, string>
     */
    public function normalizeScopes(array $scopes): array
    {
        $allowed = $this->allowedScopes();
        $allowedMap = array_fill_keys($allowed, true);

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($scope): string => trim((string) $scope),
            $scopes
        ))));

        if ($normalized === []) {
            throw new InvalidArgumentException('External token scopes must not be empty.');
        }

        foreach ($normalized as $scope) {
            if (! isset($allowedMap[$scope])) {
                throw new InvalidArgumentException("Unknown external token scope: {$scope}");
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $tokenMetadata
     */
    public function tokenHasScope(array $tokenMetadata, string $scope): bool
    {
        $tokenScopes = data_get($tokenMetadata, 'token_scopes', []);
        if (! is_array($tokenScopes)) {
            return false;
        }

        return in_array($scope, $tokenScopes, true);
    }

    /**
     * @param array<string, mixed> $tokenMetadata
     * @param array<int, string> $scopes
     */
    public function tokenHasAnyScope(array $tokenMetadata, array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->tokenHasScope($tokenMetadata, $scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $scopes
     * @return array<string, mixed>
     */
    public function issueTokenMetadata(array $scopes, ?string $name = null): array
    {
        $normalizedScopes = $this->normalizeScopes($scopes);

        return array_filter([
            'token_scopes' => $normalizedScopes,
            'token_name' => $name !== null ? trim($name) : null,
            'token_created_at' => now()->toISOString(),
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
