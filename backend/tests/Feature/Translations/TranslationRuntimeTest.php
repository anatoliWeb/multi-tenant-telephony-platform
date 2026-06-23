<?php

namespace Tests\Feature\Translations;

use App\Models\SystemTranslation;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Controllers\Api\V1\TranslationController;
use App\Services\Translation\TranslationCacheService;
use App\Services\Translation\TranslationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TranslationRuntimeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resolves_translations_by_requested_locale_then_fallback_locale(): void
    {
        config()->set('app.fallback_locale', 'en');

        SystemTranslation::create([
            'locale' => 'en',
            'group' => 'general',
            'key' => 'sync.greeting',
            'value' => 'Hello fallback',
            'source' => 'test',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        SystemTranslation::create([
            'locale' => 'uk',
            'group' => 'general',
            'key' => 'sync.greeting',
            'value' => 'Hello uk',
            'source' => 'test',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        $service = app(TranslationService::class);

        $this->assertSame('Hello uk', $service->get('sync.greeting', [], 'uk'));

        SystemTranslation::query()
            ->where('locale', 'uk')
            ->where('group', 'general')
            ->where('key', 'sync.greeting')
            ->delete();

        app(TranslationCacheService::class)->forget('uk', 'general', 'sync.greeting');

        $this->assertSame('Hello fallback', $service->get('sync.greeting', [], 'uk'));
    }

    public function test_dt_missing_key_auto_registers_untranslated_entry(): void
    {
        config()->set('app.fallback_locale', 'en');

        $value = dt('missing.translation.key', [], 'uk');

        $this->assertSame('missing.translation.key', $value);

        $row = SystemTranslation::query()
            ->where('locale', 'uk')
            ->where('group', 'general')
            ->where('key', 'missing.translation.key')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('missing.translation.key', $row->value);
        $this->assertTrue((bool) $row->is_auto_generated);
        $this->assertFalse((bool) $row->is_translated);
    }

    public function test_accept_language_changes_api_localized_response_message(): void
    {
        SystemTranslation::create([
            'locale' => 'en',
            'group' => 'notifications',
            'key' => 'success',
            'value' => 'Success EN',
            'source' => 'test',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        SystemTranslation::create([
            'locale' => 'uk',
            'group' => 'notifications',
            'key' => 'success',
            'value' => 'Success UK',
            'source' => 'test',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        Route::middleware(SetRequestLocale::class)
            ->get('/api/test-locale-response', function () {
                return response()->json([
                    'message' => dt('notifications.success'),
                    'locale' => app()->getLocale(),
                ]);
            });

        $response = $this
            ->withHeader('Accept-Language', 'uk')
            ->getJson('/api/test-locale-response');

        $response
            ->assertOk()
            ->assertJsonPath('locale', 'uk')
            ->assertJsonPath('message', 'Success UK');
    }

    public function test_translation_crud_model_flow(): void
    {
        $en = SystemTranslation::create([
            'locale' => 'en',
            'group' => 'crud',
            'key' => 'entry',
            'value' => 'Entry EN',
            'source' => 'manual',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);
        $uk = SystemTranslation::create([
            'locale' => 'uk',
            'group' => 'crud',
            'key' => 'entry',
            'value' => 'Entry UK',
            'source' => 'manual',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        $this->assertDatabaseHas('system_translations', ['id' => $en->id, 'value' => 'Entry EN']);
        $this->assertDatabaseHas('system_translations', ['id' => $uk->id, 'value' => 'Entry UK']);

        $en->update(['value' => 'Entry EN Updated']);
        $uk->update(['value' => 'Entry UK Updated']);

        $this->assertDatabaseHas('system_translations', ['id' => $en->id, 'value' => 'Entry EN Updated']);
        $this->assertDatabaseHas('system_translations', ['id' => $uk->id, 'value' => 'Entry UK Updated']);

        SystemTranslation::query()
            ->where('group', 'crud')
            ->where('key', 'entry')
            ->delete();

        $this->assertDatabaseMissing('system_translations', ['group' => 'crud', 'key' => 'entry']);
    }

    public function test_translation_update_clears_cache(): void
    {
        $translation = SystemTranslation::create([
            'locale' => 'en',
            'group' => 'general',
            'key' => 'cache.target',
            'value' => 'Old value',
            'source' => 'manual',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        $cache = app(TranslationCacheService::class);

        $this->assertSame('Old value', $cache->get('en', 'general', 'cache.target'));

        $translation->update(['value' => 'New value']);

        $this->assertSame('New value', $cache->get('en', 'general', 'cache.target'));
    }

    public function test_runtime_preload_endpoint_supports_group_filter_and_contract(): void
    {
        SystemTranslation::create([
            'locale' => 'en',
            'group' => 'roles',
            'key' => 'admin',
            'value' => 'Administrator',
            'source' => 'test',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        SystemTranslation::create([
            'locale' => 'en',
            'group' => 'permissions',
            'key' => 'users.view',
            'value' => 'View users',
            'source' => 'test',
            'is_frontend' => true,
            'is_backend' => true,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_translated' => true,
        ]);

        Route::get('/api/test-runtime-preload', [TranslationController::class, 'index']);

        $response = $this
            ->getJson('/api/test-runtime-preload?frontend=1&group=roles');

        $response
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('fallback_locale', 'en')
            ->assertJsonPath('translations.roles.admin', 'Administrator')
            ->assertJsonMissingPath('translations.permissions.users.view')
            ->assertJsonStructure([
                'locale',
                'fallback_locale',
                'translations',
                'snapshot_token',
            ]);
    }
}
