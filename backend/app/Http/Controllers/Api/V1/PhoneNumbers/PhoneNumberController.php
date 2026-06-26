<?php

namespace App\Http\Controllers\Api\V1\PhoneNumbers;

use App\Exceptions\Telephony\TelephonyConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\AssignPhoneNumberRequest;
use App\Http\Requests\Api\ListPhoneNumbersRequest;
use App\Http\Requests\Api\StorePhoneNumberRequest;
use App\Http\Requests\Api\UpdatePhoneNumberRequest;
use App\Http\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\PhoneNumbers\InboundDidResolver;
use App\Services\PhoneNumbers\PhoneNumberAssignmentService;
use App\Services\PhoneNumbers\PhoneNumberQueryService;
use App\Services\PhoneNumbers\PhoneNumberService;
use App\Services\PhoneNumbers\UserPrimaryDidResolver;
use Illuminate\Http\JsonResponse;

class PhoneNumberController extends BaseController
{
    public function __construct(
        private readonly PhoneNumberQueryService $queryService,
        private readonly PhoneNumberService $phoneNumberService,
        private readonly PhoneNumberAssignmentService $assignmentService,
        private readonly UserPrimaryDidResolver $primaryDidResolver,
        private readonly InboundDidResolver $inboundDidResolver,
    ) {
    }

    public function index(ListPhoneNumbersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PhoneNumber::class);

        $paginator = $this->queryService->paginate($request->validated());
        $items = PhoneNumberResource::collection(collect($paginator->items()))->resolve();

        return $this->paginatedResponse($paginator->setCollection(collect($items)));
    }

    public function show(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('view', $phoneNumber);
        $phoneNumber->load(['assignedUser.assignedExtensions']);

        return $this->successResponse(
            (new PhoneNumberResource($phoneNumber))->resolve()
        );
    }

    public function store(StorePhoneNumberRequest $request): JsonResponse
    {
        $this->authorize('create', PhoneNumber::class);

        try {
            /** @var User $user */
            $user = $request->user();
            $phoneNumber = $this->phoneNumberService->create($request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number created', 201);
    }

    public function update(UpdatePhoneNumberRequest $request, PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('update', $phoneNumber);

        try {
            /** @var User $user */
            $user = $request->user();
            $phoneNumber = $this->phoneNumberService->update($phoneNumber, $request->validated(), $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number updated');
    }

    public function destroy(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('delete', $phoneNumber);
        try {
            $this->phoneNumberService->delete($phoneNumber);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse(['deleted' => true], 'Phone number deleted');
    }

    public function assign(AssignPhoneNumberRequest $request, PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('assign', $phoneNumber);
        try {
            $user = User::query()->findOrFail((int) $request->validated('assigned_user_id'));
            $phoneNumber = $this->assignmentService->assign($phoneNumber, $user, (bool) $request->validated('is_primary'));
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number assigned');
    }

    public function unassign(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('assign', $phoneNumber);
        try {
            $phoneNumber = $this->assignmentService->unassign($phoneNumber);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number unassigned');
    }

    public function setPrimary(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('setPrimary', $phoneNumber);
        try {
            $phoneNumber = $this->assignmentService->setPrimary($phoneNumber);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Primary DID updated');
    }

    public function activate(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('provision', $phoneNumber);

        try {
            /** @var User $user */
            $user = request()->user();
            $phoneNumber = $this->phoneNumberService->activate($phoneNumber, $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number activated');
    }

    public function suspend(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('provision', $phoneNumber);

        try {
            /** @var User $user */
            $user = request()->user();
            $phoneNumber = $this->phoneNumberService->suspend($phoneNumber, $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number suspended');
    }

    public function release(PhoneNumber $phoneNumber): JsonResponse
    {
        if (! $phoneNumber->isInCurrentTenant()) {
            abort(404, 'Phone number not found.');
        }

        $this->authorize('release', $phoneNumber);

        try {
            /** @var User $user */
            $user = request()->user();
            $phoneNumber = $this->phoneNumberService->release($phoneNumber, $user);
        } catch (TelephonyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 409);
        }

        return $this->successResponse((new PhoneNumberResource($phoneNumber))->resolve(), 'Phone number released');
    }

    public function assignmentOptions(): JsonResponse
    {
        $this->authorize('viewAny', PhoneNumber::class);

        return $this->successResponse([
            'users' => $this->queryService->assignmentOptions()->map(function (User $user): array {
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

    public function userPhoneNumbers(User $user): JsonResponse
    {
        $this->authorize('viewAny', PhoneNumber::class);
        $phoneNumbers = $this->queryService->phoneNumbersForUser($user);

        return $this->successResponse(PhoneNumberResource::collection($phoneNumbers)->resolve());
    }

    public function userPrimaryDid(User $user): JsonResponse
    {
        $this->authorize('viewAny', PhoneNumber::class);
        $phoneNumber = $this->primaryDidResolver->resolve($user);

        return $this->successResponse(
            $phoneNumber ? (new PhoneNumberResource($phoneNumber->load(['assignedUser.assignedExtensions'])))->resolve() : null
        );
    }

    public function inboundLookup(string $number, ?string $tenantId = null): JsonResponse
    {
        $result = $this->inboundDidResolver->resolve($number, $tenantId);

        return $this->successResponse($result?->toArray());
    }
}
