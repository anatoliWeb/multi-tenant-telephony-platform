<?php


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Translation\TranslationPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Translation API controller.
 *
 * WHY:
 * Provides runtime localization payloads
 * for SPA/frontend applications.
 */
class TranslationController extends Controller
{
    /**
     * Guest-safe runtime groups.
     *
     * WHY:
     * Unauthenticated bootstrap (login page) only needs public UI copy.
     * Admin/RBAC-heavy groups stay hidden until authenticated requests.
     */
    private const GUEST_ALLOWED_GROUPS = [
        'common',
        'auth',
        'validation',
        'public',
    ];

    public function __construct(
        protected TranslationPayloadBuilder $payloadBuilder
    )
    {
    }

    /**
     * Return grouped translations.
     */
    public function index(
        Request $request
    ): JsonResponse
    {

        $locale = $request->string('locale')
            ->toString();

        $group = $request->string('group')
            ->toString();

        $locale = $locale ?: app()->getLocale();
        $group = $group ?: null;

        $frontendOnly = $request->boolean(
            'frontend'
        );

        $backendOnly = $request->boolean(
            'backend'
        );

        $payload = $this->payloadBuilder->build(
            locale: $locale,
            group: $group,
            frontendOnly: $frontendOnly,
            backendOnly: $backendOnly
        );

        if (!$request->user() && !$group) {
            $translations = $payload['translations'] ?? [];
            $payload['translations'] = collect($translations)
                ->only(self::GUEST_ALLOWED_GROUPS)
                ->all();
        }

        return response()->json($payload);
    }
}
