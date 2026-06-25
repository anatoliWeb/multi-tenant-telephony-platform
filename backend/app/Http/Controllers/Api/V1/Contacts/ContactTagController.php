<?php

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\StoreContactTagRequest;
use App\Http\Requests\Api\UpdateContactTagRequest;
use App\Http\Resources\ContactTagResource;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\ContactTag;
use App\Services\Contacts\ContactQueryService;
use App\Services\Contacts\ContactService;
use Illuminate\Http\JsonResponse;

class ContactTagController extends BaseController
{
    public function __construct(
        private readonly ContactQueryService $queryService,
        private readonly ContactService $contactService,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ContactTag::class);

        return $this->successResponse(ContactTagResource::collection($this->queryService->tags())->resolve());
    }

    public function store(StoreContactTagRequest $request): JsonResponse
    {
        $this->authorize('create', ContactTag::class);

        try {
            $tag = $this->contactService->createTag($request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new ContactTagResource($tag))->resolve(), 'Tag created', 201);
    }

    public function update(UpdateContactTagRequest $request, ContactTag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        try {
            $tag = $this->contactService->updateTag($tag, $request->validated());
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new ContactTagResource($tag))->resolve(), 'Tag updated');
    }

    public function destroy(ContactTag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);
        $this->contactService->deleteTag($tag);

        return $this->successResponse(['deleted' => true], 'Tag deleted');
    }
}
