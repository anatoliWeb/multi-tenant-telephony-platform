<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreSystemSettingRequest;
use App\Http\Requests\Api\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Localization\TranslationUpsertService;
use App\Services\Settings\SettingsQueryService;
use App\Services\Settings\SettingsService;
use App\Services\SettingsResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Runtime settings API controller.
 *
 * WHY THIS CONTROLLER EXISTS:
 * Platform settings are dynamic runtime configuration records and must support:
 * - CRUD management
 * - inheritance debugging
 * - effective value inspection
 * - frontend preload hydration
 * - feature flags
 * - future tenant-aware resolution
 *
 * IMPORTANT:
 * This controller should orchestrate only:
 * - validation
 * - response formatting
 * - service calls
 *
 * Business logic must remain inside:
 * - SettingsService
 * - SettingsResolverService
 * - SettingsCacheService
 */
class SettingsController extends BaseController
{
    public function __construct(
        protected SettingsService $settings,
        protected SettingsQueryService $settingsQuery,
        protected TranslationUpsertService $translationUpsert
    ) {
    }

    /**
     * List runtime settings and effective resolved values.
     *
     * WHY:
     * Admin UI must inspect:
     * - raw setting records
     * - inheritance layers
     * - effective runtime values
     * - grouped configuration structure
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->settingsQuery->listForApi(
            filters: $request->query(),
            defaultUserId: auth()->id()
        );

        return $this->successResponse([
            'settings' => SystemSettingResource::collection($result['settings'])
                ->resolve(),

            'effective' => $result['effective'],

            'groups' => $result['groups'],

            'types' => $result['types'],

            'meta' => $result['meta'],
        ], dt('notifications.success'));
    }

    /**
     * Create runtime setting.
     *
     * IMPORTANT:
     * Actual serialization/casting/inheritance logic is delegated
     * to SettingsService.
     */
    public function store(
        StoreSystemSettingRequest $request
    ): JsonResponse {

        $validated = $request->validated();

        $setting = $this->settings->set(
            key: $validated['key'],
            value: $validated['value'] ?? null,

            attributes: [
                ...$validated,

                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
        $this->persistSettingTranslations($setting->key, $validated['translations'] ?? []);

        return $this->successResponse(
            (new SystemSettingResource(
                $setting->fresh([
                    'scopeUser:id,name',
                    'scopeRole:id,name',
                    'scopePermission:id,name',
                ])
            ))->resolve(),

            dt('notifications.created'),

            201
        );
    }

    /**
     * Update runtime setting.
     *
     * IMPORTANT:
     * Effective runtime caches are invalidated automatically
     * by SettingsService.
     */
    public function update(
        UpdateSystemSettingRequest $request,
        SystemSetting $setting
    ): JsonResponse {

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Preserve Existing Scope Ownership
        |--------------------------------------------------------------------------
        */

        $attributes = [
            ...$validated,

            'scope_user_id' => $validated['scope_user_id']
                ?? $setting->scope_user_id,

            'scope_role_id' => $validated['scope_role_id']
                ?? $setting->scope_role_id,

            'scope_permission_id' => $validated['scope_permission_id']
                ?? $setting->scope_permission_id,

            'updated_by' => auth()->id(),
        ];

        $updated = $this->settings->set(
            key: $setting->key,

            value: $validated['value']
            ?? $setting->value,

            attributes: $attributes
        );
        $this->persistSettingTranslations($updated->key, $validated['translations'] ?? []);

        return $this->successResponse(
            (new SystemSettingResource(
                $updated->fresh([
                    'scopeUser:id,name',
                    'scopeRole:id,name',
                    'scopePermission:id,name',
                ])
            ))->resolve(),

            dt('notifications.updated')
        );
    }

    /**
     * Delete runtime setting.
     */
    public function destroy(
        SystemSetting $setting
    ): JsonResponse {

        $setting->delete();

        $this->settings->invalidateCaches();

        return $this->successResponse([
            'deleted' => true,
        ], dt('notifications.deleted'));
    }

    /**
     * Resolve effective runtime value with inheritance metadata.
     *
     * WHY:
     * Admin/debug tools must understand:
     * - which value won
     * - which inheritance scope resolved it
     * - where the value originated from
     */
    public function effective(
        Request $request
    ): JsonResponse {

        $request->validate([
            'key' => ['required', 'string', 'max:160'],

            'for_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'channel' => [
                'nullable',
                'string',
                'in:frontend,backend',
            ],
        ]);

        $userId = $request->integer('for_user_id')
            ?: auth()->id();

        $channel = $this->settingsQuery->normalizeChannel(
            $request->query('channel')
        );

        $key = (string) $request->query('key');

        $user = $userId
            ? User::find($userId)
            : null;

        $result = $this->settings->getDetailed(
            key: $key,
            channel: $channel,
            user: $user
        );

        return $this->successResponse(
            $result,
            dt('notifications.success')
        );
    }

    /**
     * Frontend runtime preload endpoint.
     *
     * WHY:
     * SPA applications preload runtime settings during bootstrap
     * to avoid:
     * - duplicated API calls
     * - hydration flickering
     * - runtime configuration waterfalls
     *
     * IMPORTANT:
     * Only frontend-safe settings are exposed here.
     */
    public function preload(
        Request $request
    ): JsonResponse {

        $userId = auth()->id();

        if (! $userId) {
            return $this->errorResponse(
                dt('notifications.error'),
                401
            );
        }

        $user = User::find($userId);

        if (! $user) {
            return $this->errorResponse(
                dt('notifications.error'),
                404
            );
        }

        $payload = $this->settings->preloadFrontend($user);

        return $this->successResponse(
            $payload,
            dt('notifications.success')
        );
    }

    /**
     * @param array<string, array{label?: string|null, description?: string|null}> $translations
     */
    protected function persistSettingTranslations(string $settingKey, array $translations): void
    {
        if ($translations === []) {
            return;
        }

        $labels = [];
        $descriptions = [];

        foreach ($translations as $locale => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $labels[$locale] = isset($entry['label']) ? (string) $entry['label'] : null;
            $descriptions[$locale] = isset($entry['description']) ? (string) $entry['description'] : null;
        }

        $this->translationUpsert->saveTranslations('settings', $settingKey, $labels, true, true);
        $this->translationUpsert->saveTranslations('settings', $settingKey . '.description', $descriptions, true, true);
    }
}
