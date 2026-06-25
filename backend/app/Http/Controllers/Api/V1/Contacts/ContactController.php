<?php

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ListContactsRequest;
use App\Http\Requests\Api\LookupContactByPhoneRequest;
use App\Http\Requests\Api\StoreContactRequest;
use App\Http\Requests\Api\UpdateContactRequest;
use App\Http\Resources\ContactLookupResource;
use App\Http\Resources\ContactResource;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Models\Contact;
use App\Models\User;
use App\Services\Contacts\ContactQueryService;
use App\Services\Contacts\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends BaseController
{
    public function __construct(
        private readonly ContactQueryService $queryService,
        private readonly ContactService $contactService,
    ) {
    }

    public function index(ListContactsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $paginator = $this->queryService->paginate($request->validated());
        $items = ContactResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function search(ListContactsRequest $request): JsonResponse
    {
        return $this->index($request);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);
        $contact->load(['phones', 'emails', 'tags']);

        return $this->successResponse((new ContactResource($contact))->resolve());
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        try {
            /** @var User $user */
            $user = $request->user();
            $contact = $this->contactService->create($request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new ContactResource($contact))->resolve(), 'Contact created', 201);
    }

    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        try {
            /** @var User $user */
            $user = $request->user();
            $contact = $this->contactService->update($contact, $request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new ContactResource($contact))->resolve(), 'Contact updated');
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);
        $this->contactService->delete($contact);

        return $this->successResponse(['deleted' => true], 'Contact deleted');
    }

    public function lookupPhone(LookupContactByPhoneRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $contact = $this->queryService->lookupByPhone((string) $request->validated('phone'));
        if (! $contact instanceof Contact) {
            return $this->errorResponse('Contact not found', null, 404);
        }

        return $this->successResponse((new ContactLookupResource($contact))->resolve());
    }
}
