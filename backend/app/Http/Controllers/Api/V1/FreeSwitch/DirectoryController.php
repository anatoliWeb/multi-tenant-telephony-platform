<?php

namespace App\Http\Controllers\Api\V1\FreeSwitch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FreeSwitch\DirectoryLookupRequest;
use App\Services\FreeSwitch\FreeSwitchDirectoryService;
use Illuminate\Http\Response;

class DirectoryController extends Controller
{
    public function __construct(
        private readonly FreeSwitchDirectoryService $directoryService,
    ) {
    }

    public function show(DirectoryLookupRequest $request): Response
    {
        $xml = $this->directoryService->resolve(
            (string) $request->validated('user'),
            (string) $request->validated('domain'),
        );

        if ($xml === null) {
            abort(404);
        }

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
