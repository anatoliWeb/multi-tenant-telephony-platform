<?php

namespace App\Http\Controllers\Api\V1\Extensions;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ListExtensionsRequest;
use App\Http\Requests\Api\StoreExtensionRequest;
use App\Http\Requests\Api\UpdateExtensionRequest;
use App\Http\Resources\ExtensionResource;
use App\Models\Extension;
use App\Models\User;
use App\Services\Extensions\ExtensionQueryService;
use App\Services\Extensions\ExtensionService;
use Illuminate\Http\JsonResponse;

class ExtensionController extends BaseController
{
    public function __construct(
        private readonly ExtensionQueryService $queryService,
        private readonly ExtensionService $extensionService,
    ) {
    }

    public function index(ListExtensionsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Extension::class);

        $paginator = $this->queryService->paginate($request->validated());
        $items = ExtensionResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function show(Extension $extension): JsonResponse
    {
        if (! $extension->isInCurrentTenant()) {
            abort(404, 'Extension not found.');
        }

        $this->authorize('view', $extension);

        $extension = $this->extensionService->syncProviderState($extension->load(['credential', 'assignedUser', 'assignedContact']));

        return $this->successResponse((new ExtensionResource($extension))->resolve());
    }

    public function store(StoreExtensionRequest $request): JsonResponse
    {
        $this->authorize('create', Extension::class);

        try {
            /** @var User $user */
            $user = $request->user();
            $result = $this->extensionService->create($request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        $payload = (new ExtensionResource($result['extension']))->resolve();
        $payload['plain_secret'] = $result['plain_secret'];

        return $this->successResponse($payload, 'Extension created', 201);
    }

    public function update(UpdateExtensionRequest $request, Extension $extension): JsonResponse
    {
        if (! $extension->isInCurrentTenant()) {
            abort(404, 'Extension not found.');
        }

        $this->authorize('update', $extension);

        try {
            /** @var User $user */
            $user = $request->user();
            $extension = $this->extensionService->update($extension, $request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new ExtensionResource($extension))->resolve(), 'Extension updated');
    }

    public function destroy(Extension $extension): JsonResponse
    {
        if (! $extension->isInCurrentTenant()) {
            abort(404, 'Extension not found.');
        }

        $this->authorize('delete', $extension);
        $this->extensionService->delete($extension);

        return $this->successResponse(['deleted' => true], 'Extension deleted');
    }

    public function rotateCredentials(Extension $extension): JsonResponse
    {
        if (! $extension->isInCurrentTenant()) {
            abort(404, 'Extension not found.');
        }

        $this->authorize('manageCredentials', $extension);

        /** @var User $user */
        $user = request()->user();
        $result = $this->extensionService->rotateCredentials($extension, $user);
        $payload = (new ExtensionResource($result['extension']))->resolve();
        $payload['plain_secret'] = $result['plain_secret'];

        return $this->successResponse($payload, 'Extension credentials rotated');
    }

    public function assignmentOptions(): JsonResponse
    {
        $this->authorize('viewAny', Extension::class);
        $options = $this->queryService->assignmentOptions();

        return $this->successResponse([
            'users' => $options['users']->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all(),
            'contacts' => $options['contacts']->map(fn ($contact): array => [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'company_name' => $contact->company_name,
            ])->values()->all(),
        ]);
    }
}
