<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Token API resource.
 *
 * WHY THIS RESOURCE EXISTS:
 * Token responses are security-sensitive and should expose only a deliberate
 * subset of fields required by clients.
 *
 * WHY NOT RETURN RAW MODELS:
 * Raw token models include internal attributes and implementation details that
 * should not be part of a public API contract.
 *
 * WHAT THIS RESOURCE CONTROLS:
 * It standardizes token payload shape for list/create flows and keeps owner
 * information consistent across endpoints.
 */
class TokenResource extends JsonResource
{
    /**
     * Transform token payload into stable API structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = 'active';
        $scopes = array_values(data_get($this->resource, 'abilities', []));
        $scopeLabels = collect($scopes)->mapWithKeys(fn (string $scope) => [
            $scope => $this->translateWithFallback('permissions.' . $scope, $scope),
        ]);

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'created_at' => data_get($this->resource, 'created_at'),
            'status' => $status,
            'status_label' => $this->translateWithFallback('tokens.status.' . $status, $status),
            'labels' => [
                'name' => $this->translateWithFallback('tokens.columns.name', 'Name'),
                'created_at' => $this->translateWithFallback('tokens.columns.created_at', 'Created'),
            ],
            'owner' => [
                'id' => data_get($this->resource, 'owner.id'),
                'name' => data_get($this->resource, 'owner.name'),
            ],
            'scopes' => $scopes,
            'scope_labels' => $scopeLabels,
            'scopes_count' => count($scopes),
        ];
    }

    protected function translateWithFallback(string $key, string $fallback): string
    {
        $translated = dt($key);
        return $translated === $key ? $fallback : $translated;
    }
}
