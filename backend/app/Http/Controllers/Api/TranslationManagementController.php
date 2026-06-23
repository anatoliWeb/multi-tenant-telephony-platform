<?php

namespace App\Http\Controllers\Api;

use App\Support\Api\ApiResponse;
use App\Services\Translation\TranslationManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Translation management controller.
 *
 * WHY THIS CONTROLLER EXISTS:
 * Runtime translation preload endpoint (`/v1/translations`) is read-focused
 * and optimized for SPA hydration.
 *
 * Admin translation management needs a separate endpoint because it requires:
 * - pagination
 * - filters
 * - locale matrix structure
 * - create/update/delete operations
 * - cache invalidation
 * - missing translation visibility
 */
class TranslationManagementController extends BaseController
{
    public function __construct(
        protected TranslationManagementService $translationManagementService
    ) {
    }

    /**
     * Return translation keys grouped as locale matrix rows.
     *
     * WHY:
     * Admin UI should edit translations by logical key, not by raw DB rows.
     *
     * Instead of showing:
     * - roles.admin EN
     * - roles.admin UK
     * - roles.admin DE
     *
     * we return one row:
     * - roles.admin
     *   - en
     *   - uk
     *   - de
     */
    public function index(Request $request): JsonResponse
    {
        $payload = $this->translationManagementService->listForApi($request->query());

        return ApiResponse::success(
            $payload['items'],
            dt('notifications.success'),
            200,
            $payload['meta']
        );
    }

    /**
     * Create a new translation key with values for one or more locales.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:191'],
            'source' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_auto_generated' => ['nullable', 'boolean'],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['nullable', 'string'],
        ]);

        $this->translationManagementService->create($validated);

        return $this->successResponse(['saved' => true], dt('notifications.created'), 201);
    }

    /**
     * Update all locale values for an existing logical translation key.
     */
    public function update(Request $request, int $translation): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_auto_generated' => ['nullable', 'boolean'],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['nullable', 'string'],
        ]);

        $this->translationManagementService->updateById($translation, $validated);

        return $this->successResponse(['saved' => true], dt('notifications.updated'));
    }

    /**
     * Delete the full translation matrix for selected group/key/source.
     */
    public function destroy(int $translation): JsonResponse
    {
        $this->translationManagementService->deleteById($translation);

        return $this->successResponse(['deleted' => true], dt('notifications.deleted'));
    }
}
