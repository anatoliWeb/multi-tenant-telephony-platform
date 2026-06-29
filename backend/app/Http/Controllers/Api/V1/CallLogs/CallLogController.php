<?php

namespace App\Http\Controllers\Api\V1\CallLogs;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\CallLogStatisticsRequest;
use App\Http\Requests\Api\ListCallLogsRequest;
use App\Http\Resources\CallEventResource;
use App\Http\Resources\CallLogResource;
use App\Models\CallLog;
use App\Models\User;
use App\Services\CallLogs\CallExportService;
use App\Services\CallLogs\CallQueryService;
use App\Services\CallLogs\CallStatisticsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallLogController extends BaseController
{
    public function __construct(
        private readonly CallQueryService $callQueryService,
        private readonly CallStatisticsService $callStatisticsService,
        private readonly CallExportService $callExportService,
    ) {
    }

    public function index(ListCallLogsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CallLog::class);

        /** @var User $user */
        $user = $request->user();
        $paginator = $this->callQueryService->paginate($user, $request->validated());
        $items = CallLogResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function show(CallLog $callLog): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $visibleCallLog = $this->callQueryService->findVisible($user, $callLog);
        if (! $visibleCallLog instanceof CallLog) {
            abort(404, 'Call log not found.');
        }

        $this->authorize('view', $visibleCallLog);

        return $this->successResponse((new CallLogResource($visibleCallLog))->resolve());
    }

    public function statistics(CallLogStatisticsRequest $request): JsonResponse
    {
        $this->authorize('viewStatistics', CallLog::class);

        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $this->callStatisticsService->summarize($user, $request->validated())
        );
    }

    public function export(ListCallLogsRequest $request): StreamedResponse
    {
        $this->authorize('export', CallLog::class);

        /** @var User $user */
        $user = $request->user();

        return $this->callExportService->export($user, $request->validated());
    }

    public function events(CallLog $callLog): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $visibleCallLog = $this->callQueryService->findVisible($user, $callLog);
        if (! $visibleCallLog instanceof CallLog) {
            abort(404, 'Call log not found.');
        }

        $this->authorize('view', $visibleCallLog);

        return $this->successResponse(
            CallEventResource::collection($visibleCallLog->events)->resolve()
        );
    }

    public function filterOptions(): JsonResponse
    {
        $this->authorize('viewAny', CallLog::class);

        /** @var User $user */
        $user = request()->user();
        if (! $user->hasPermission('call_logs.view_all')) {
            return $this->successResponse(['users' => []]);
        }

        return $this->successResponse([
            'users' => $this->callQueryService->userFilterOptions()->map(function (User $user): array {
                $extension = $user->assignedExtensions->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'extension' => $extension ? [
                        'id' => $extension->id,
                        'number' => $extension->number,
                        'label' => $extension->label,
                    ] : null,
                ];
            })->values()->all(),
        ]);
    }
}
