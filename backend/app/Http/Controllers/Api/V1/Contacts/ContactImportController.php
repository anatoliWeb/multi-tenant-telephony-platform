<?php

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ImportContactsRequest;
use App\Http\Requests\Api\ImportContactsValidateRequest;
use App\Models\Contact;
use App\Services\Contacts\ContactImportService;
use Illuminate\Http\JsonResponse;

class ContactImportController extends BaseController
{
    public function __construct(
        private readonly ContactImportService $importService
    ) {
    }

    public function validateImport(ImportContactsValidateRequest $request): JsonResponse
    {
        $this->authorize('import', Contact::class);

        return $this->successResponse($this->importService->validate($request->file('file')));
    }

    public function import(ImportContactsRequest $request): JsonResponse
    {
        $this->authorize('import', Contact::class);

        return $this->successResponse($this->importService->import($request->file('file'), $request->user()), 'Contacts imported', 201);
    }
}
