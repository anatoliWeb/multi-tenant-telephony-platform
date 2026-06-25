<?php

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExportContactsRequest;
use App\Models\Contact;
use App\Services\Contacts\ContactExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function __construct(
        private readonly ContactExportService $exportService
    ) {
    }

    public function __invoke(ExportContactsRequest $request): StreamedResponse
    {
        $this->authorize('export', Contact::class);

        return $this->exportService->export($request->validated());
    }
}
