<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\MetaResource;
use App\Services\MetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaController extends BaseController
{
    public function __construct(
        protected MetaService $metaService
    ) {
    }

    /**
     * Return frontend metadata.
     *
     * WHY:
     * Frontend needs roles and permissions for forms,
     * buttons and conditional UI rendering.
     */
    public function index(): JsonResponse
    {
        return $this->renderMetaPayload(fn () => $this->metaService->getMeta());
    }

    public function bootstrap(): JsonResponse
    {
        return $this->renderMetaPayload(fn () => $this->metaService->getBootstrapMeta());
    }

    public function rbac(): JsonResponse
    {
        return $this->renderMetaPayload(fn () => $this->metaService->getRbacMeta());
    }

    protected function renderMetaPayload(callable $payloadFactory): JsonResponse
    {
        try {
            return $this->successResponse(
                (new MetaResource($payloadFactory()))->resolve(),
                dt('notifications.success')
            );
        } catch (Throwable $exception) {
            $authUser = auth()->user();
            Log::error('MetaController::index failed', [
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
                'route' => request()->route()?->getName(),
                'path' => request()->path(),
                'guard' => config('auth.defaults.guard'),
                'app_env' => app()->environment(),
                'auth_user_type' => is_object($authUser) ? $authUser::class : gettype($authUser),
                'trace_head' => $exception->getTrace()[0] ?? null,
            ]);

            return $this->errorResponse(dt('notifications.error'), null, 500);
        }
    }
}
