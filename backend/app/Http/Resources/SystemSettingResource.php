<?php

namespace App\Http\Resources;

use App\Models\SystemSetting;
use App\Services\Settings\SettingsResolverService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * System setting API resource.
 *
 * WHY RESOURCE LAYER EXISTS:
 * Settings are a long-lived platform contract shared between:
 * - Vue admin
 * - future Angular dashboard
 * - runtime preload endpoints
 * - feature flag consumers
 * - future mobile clients
 *
 * This resource isolates frontend payloads from internal model evolution.
 *
 * IMPORTANT:
 * Resource transformation must stay lightweight because settings collections
 * may be rendered frequently during:
 * - admin rendering
 * - SPA bootstrap
 * - preload hydration
 * - effective settings previews
 *
 * PERFORMANCE NOTES:
 * - avoid resolving services repeatedly
 * - avoid N+1 relations
 * - avoid repeated translation DB lookups
 */
class SystemSettingResource extends JsonResource
{
    /**
     * Shared resolver instance.
     *
     * WHY:
     * Avoid repeated container resolution for every resource item.
     */
    protected static ?SettingsResolverService $resolver = null;

    /**
     * Cached translation payload.
     *
     * IMPORTANT:
     * Controller should preload translations and inject them through:
     *
     * additional([
     *     'translations' => [...]
     * ])
     *
     * This prevents translation lookup storms.
     *
     * @var array<string, string>
     */
    protected static array $translations = [];

    /**
     * Inject preloaded translations.
     *
     * Used by collection controllers to avoid:
     * - repeated dt() calls
     * - translation cache storms
     * - resource-level DB fallback lookups
     *
     * @param array<string, string> $translations
     */
    public static function preloadTranslations(array $translations): void
    {
        static::$translations = $translations;
    }

    /**
     * Resolve shared settings resolver instance.
     */
    protected function resolver(): SettingsResolverService
    {
        if (! static::$resolver) {
            static::$resolver = app(SettingsResolverService::class);
        }

        return static::$resolver;
    }

    /**
     * Transform setting into stable frontend contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SystemSetting $setting */
        $setting = $this->resource;

        return [
            'id' => $setting->id,

            'key' => $setting->key,

            /*
            |--------------------------------------------------------------------------
            | Localized Presentation
            |--------------------------------------------------------------------------
            |
            | Localized labels/descriptions are frontend-safe presentation values.
            | Internal setting identifiers remain immutable.
            */
            'label' => $this->resolveLabel($setting),

            'group' => $setting->group,

            'description' => $this->resolveDescription($setting),

            'translation_key' => $this->resolveTranslationKey($setting),

            /*
            |--------------------------------------------------------------------------
            | Typed Runtime Values
            |--------------------------------------------------------------------------
            */
            'type' => $setting->type,

            'value' => $this->resolver()->castValue(
                $setting->value,
                $setting->type
            ),

            'default_value' => $this->resolver()->castValue(
                $setting->default_value,
                $setting->type
            ),

            /*
            |--------------------------------------------------------------------------
            | Visibility / Runtime Flags
            |--------------------------------------------------------------------------
            */
            'is_frontend' => $setting->is_frontend,

            'is_backend' => $setting->is_backend,

            'is_public' => $setting->is_public,

            'is_encrypted' => $setting->is_encrypted,

            /*
            |--------------------------------------------------------------------------
            | Inheritance Metadata
            |--------------------------------------------------------------------------
            */
            'priority' => $setting->priority,

            'inheritance_source' => $setting->inheritance_source,

            'is_active' => $setting->is_active,

            'is_system' => $setting->is_system,

            /*
            |--------------------------------------------------------------------------
            | Scope Information
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Relations should always be eager loaded in controller:
            |
            | ->with([
            |     'scopeUser:id,name',
            |     'scopeRole:id,name',
            |     'scopePermission:id,name',
            | ])
            */
            'scope' => [
                'type' => $setting->scope_type,

                'user_id' => $setting->scope_user_id,

                'role_id' => $setting->scope_role_id,

                'permission_id' => $setting->scope_permission_id,

                'user' => $setting->scopeUser ? [
                    'id' => $setting->scopeUser->id,
                    'name' => $setting->scopeUser->name,
                ] : null,

                'role' => $setting->scopeRole ? [
                    'id' => $setting->scopeRole->id,
                    'name' => $setting->scopeRole->name,
                ] : null,

                'permission' => $setting->scopePermission ? [
                    'id' => $setting->scopePermission->id,
                    'name' => $setting->scopePermission->name,
                ] : null,
            ],

            'created_at' => $setting->created_at?->toISOString(),

            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }

    /**
     * Resolve localized setting label.
     *
     * IMPORTANT:
     * Uses preloaded translation map first to avoid repeated dt() calls.
     */
    protected function resolveLabel(SystemSetting $setting): string
    {
        $translationKey = $this->resolveTranslationKey($setting);
        $translated = static::$translations[$translationKey]
            ?? dt($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return (string) $setting->label;
    }

    /**
     * Resolve localized setting description.
     *
     * IMPORTANT:
     * Uses preloaded translation map first to avoid translation storms.
     */
    protected function resolveDescription(SystemSetting $setting): ?string
    {
        $descriptionKey = $this->resolveTranslationKey($setting)
            . '.description';
        $translated = static::$translations[$descriptionKey]
            ?? dt($descriptionKey);

        if ($translated !== $descriptionKey) {
            return $translated;
        }

        return $setting->description;
    }

    /**
     * Resolve stable translation key.
     *
     * WHY:
     * Allows explicit translation override while preserving
     * automatic convention fallback.
     */
    protected function resolveTranslationKey(SystemSetting $setting): string
    {
        $explicit = data_get(
            $setting->getAttributes(),
            'translation_key'
        );

        if (
            is_string($explicit)
            && $explicit !== ''
        ) {
            return $explicit;
        }

        return 'settings.' . $setting->key;
    }
}
