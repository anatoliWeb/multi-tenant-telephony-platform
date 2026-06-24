<?php

namespace App\Services\Monitoring;

use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class StructuredLogContextService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function sanitize(array $context): array
    {
        if (! (bool) config('logging.structured.enabled', true)) {
            return $context;
        }

        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalized = mb_strtolower((string) $key);

            if (in_array($normalized, $this->forbiddenKeys(), true)) {
                continue;
            }

            if (is_array($value)) {
                // WHY:
                // Sensitive fields can be nested in payload fragments.
                // Recursive sanitization prevents accidental leakage from deep context structures.
                $sanitized[(string) $key] = $this->sanitize($value);
                continue;
            }

            $sanitized[(string) $key] = $value;
        }

        return $sanitized;
    }

    /**
     * @return array<string>
     */
    public function forbiddenKeys(): array
    {
        return (array) config('logging.structured.forbidden_keys', [
            'token',
            'access_token',
            'refresh_token',
            'token_hash',
            'authorization',
            'cookie',
            'cookies',
            'password',
            'secret',
            'signature',
            'webhook_secret',
            'raw_payload',
            'raw_response',
            'payload',
            'response_body',
            'device_key',
            'user_agent',
            'ip_address',
            'disk',
            'checksum',
            'storage_path',
        ]);
    }

    /**
     * @return array{error_class: string, error_summary: string}
     */
    public function summarizeThrowable(Throwable $exception): array
    {
        return [
            'error_class' => $exception::class,
            'error_summary' => mb_substr($exception->getMessage(), 0, 255),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function withRequestContext(Request $request, array $context = []): array
    {
        $requestId = (string) ($request->attributes->get('request_id') ?? '');

        if ($requestId === '') {
            // WHY:
            // Queue/realtime/error logs from the same request should share one correlation key.
            // If upstream does not provide X-Request-Id, we generate one once and reuse it.
            $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid()->toString());
            $request->attributes->set('request_id', $requestId);
        }

        $routeName = $request->route()?->getName();

        return $this->sanitize($context + [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => is_string($routeName) ? $routeName : null,
            'user_id' => optional($request->user())->id,
            'tenant_id' => $this->tenantContext->tenantId(),
            'tenant_slug' => $this->tenantContext->tenant()?->slug,
        ]);
    }
}
