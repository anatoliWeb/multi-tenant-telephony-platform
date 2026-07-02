<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MonitoringHealthController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\TranslationManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\User\TenantController;
use App\Http\Controllers\Api\V1\Chat\ChatConversationController;
use App\Http\Controllers\Api\V1\Chat\ChatDeviceController;
use App\Http\Controllers\Api\V1\Chat\ChatConversationParticipantController;
use App\Http\Controllers\Api\V1\Chat\ChatMessageController;
use App\Http\Controllers\Api\V1\Chat\ChatAttachmentController;
use App\Http\Controllers\Api\V1\Chat\ChatReadStateController;
use App\Http\Controllers\Api\V1\Chat\ChatTypingController;
use App\Http\Controllers\Api\V1\Chat\ChatPresenceController;
use App\Http\Controllers\Api\V1\Chat\ChatWebhookEndpointController;
use App\Http\Controllers\Api\V1\Chat\ChatIncomingWebhookController;
use App\Http\Controllers\Api\V1\Contacts\ContactController;
use App\Http\Controllers\Api\V1\Contacts\ContactExportController;
use App\Http\Controllers\Api\V1\Contacts\ContactImportController;
use App\Http\Controllers\Api\V1\Contacts\ContactTagController;
use App\Http\Controllers\Api\V1\Ivr\IvrMenuController;
use App\Http\Controllers\Api\V1\Ivr\IvrOptionController;
use App\Http\Controllers\Api\V1\CallQueues\CallQueueController;
use App\Http\Controllers\Api\V1\CallQueues\CallQueueMemberController;
use App\Http\Controllers\Api\V1\CallLogs\CallLogController;
use App\Http\Controllers\Api\V1\Extensions\ExtensionController;
use App\Http\Controllers\Api\V1\FreeSwitch\DirectoryController;
use App\Http\Controllers\Api\V1\RingGroups\RingGroupController;
use App\Http\Controllers\Api\V1\RingGroups\RingGroupMemberController;
use App\Http\Controllers\Api\V1\PhoneNumbers\PhoneNumberController;
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\Api\V1\TranslationController;

/**
 * ----------------------------------------------------------------
 * API Routes
 * ----------------------------------------------------------------
 *
 * This file contains all API endpoints for the application.
 *
 * ARCHITECTURE:
 * - API-first backend
 * - Stateless authentication
 * - JSON responses only
 * - Shared contract for Angular/Vue/mobile clients
 *
 * IMPORTANT:
 * All API endpoints must follow the standardized
 * response structure defined in BaseController
 * and global exception handling.
 */

/**
 * ----------------------------------------------------------------
 * Legacy Flat API Routes
 * ----------------------------------------------------------------
 *
 * TEMPORARY:
 * Current routes are kept for backward compatibility
 * during transition to versioned API architecture.
 *
 * These routes will eventually be migrated to:
 * /api/v1/*
 */

/**
 * Health Check Endpoint
 */
Route::get('/health', [HealthController::class, 'show']);

/**
 * Authentication Endpoints
 */
Route::post('/token', [AuthController::class, 'token'])->middleware('throttle:auth-login');
Route::post('/login', [AuthController::class, 'token'])->middleware('throttle:auth-login');


Route::middleware(['web'])->prefix('v1/auth/session')->group(function () {

    Route::post('/login', [AuthController::class, 'sessionLogin'])->middleware('throttle:auth-login');
    Route::get('/me', [AuthController::class, 'sessionUser']);
    Route::post('/logout', [AuthController::class, 'sessionLogout']);

});

/**
 * Protected Legacy Routes
 */
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.edit');

    Route::patch('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.edit');

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete');

    Route::get('/stats', [StatsController::class, 'index']);

    Route::get('/meta', [MetaController::class, 'index']);
    Route::get('/meta/bootstrap', [MetaController::class, 'bootstrap']);
    Route::get('/meta/rbac', [MetaController::class, 'rbac']);

    Route::get('/tokens', [TokenController::class, 'index'])
        ->middleware('permission:tokens.view');

    Route::post('/tokens', [TokenController::class, 'store'])
        ->middleware('permission:tokens.create');

    Route::delete('/tokens/{id}', [TokenController::class, 'destroy'])
        ->middleware('permission:tokens.delete');
});

/**
 * ---------------------------------------------------------
 * Notifications
 * ---------------------------------------------------------
 */
Route::middleware('auth:sanctum')
    ->prefix('notifications')
    ->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])
        ->middleware('permission:notifications.view');

    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])
        ->middleware('permission:notifications.view');

    Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->middleware('permission:notifications.view');

    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead'])
        ->middleware('permission:notifications.view');

    Route::delete('/{notification}', [NotificationController::class, 'destroy'])
        ->middleware('permission:notifications.delete');

    Route::get('/preferences', [NotificationController::class, 'preferences'])
        ->middleware('permission:notifications.view');

    Route::patch('/preferences', [NotificationController::class, 'updatePreferences'])
        ->middleware('permission:notifications.view');
});

/**
 * ----------------------------------------------------------------
 * API Version 1
 * ----------------------------------------------------------------
 *
 * WHY:
 * Versioned APIs allow:
 * - safe future API changes
 * - frontend compatibility
 * - mobile app support
 * - easier microservice extraction
 * - long-term maintainability
 *
 * TARGET STRUCTURE:
 *
 * /api/v1/auth/login
 * /api/v1/users
 * /api/v1/stats
 *
 * NOTE:
 * Current implementation reuses existing controllers
 * during migration to versioned architecture.
 */

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function () {

        /**
         * --------------------------------------------------------
         * Public v1 Endpoints
         * --------------------------------------------------------
         */

        Route::get('/health', [HealthController::class, 'show'])
            ->name('health');

        /**
         * --------------------------------------------------------
         * Authentication
         * --------------------------------------------------------
         */

        Route::prefix('auth')
            ->as('auth.')
            ->group(function () {

                Route::post('/login', [AuthController::class, 'token'])
                    ->middleware('throttle:auth-login')
                    ->name('login');

                Route::post('/token', [AuthController::class, 'token'])
                    ->middleware('throttle:auth-login')
                    ->name('token');

                Route::post('/session/login', [AuthController::class, 'sessionLogin'])
                    ->middleware('web')
                    ->middleware('throttle:auth-login')
                    ->name('session.login');
            });

        Route::post('/chat/external/webhooks/{endpoint:uuid}', [ChatIncomingWebhookController::class, 'handle'])
            ->middleware('throttle:chat-external-api')
            ->name('chat.external.webhooks.handle');

        Route::post('/chat/external/messages', [ChatMessageController::class, 'storeExternal'])
            ->middleware('throttle:chat-external-api')
            ->middleware('external.chat.scope:chat.external.messages.send')
            ->name('chat.external.messages.store');

        /**
         * --------------------------------------------------------
         * Public runtime localization preload
         * --------------------------------------------------------
         *
         * WHY:
         * Bootstrap must work for guests (login screen included), so runtime
         * translation preload cannot be protected by auth:sanctum.
         */
        Route::prefix('translations')
            ->as('translations.')
            ->group(function () {
                Route::get('/', [TranslationController::class, 'index'])
                    ->name('index');
            });

        /**
         * --------------------------------------------------------
         * Local FreeSWITCH scaffolding
         * --------------------------------------------------------
         *
         * WHY:
         * The directory endpoint stays local-only until DB-backed
         * provisioning is intentionally wired into FreeSWITCH. The route uses
         * an explicit tenant/config gate instead of guessing tenant identity
         * from a raw PBX request.
         */
        Route::prefix('freeswitch')
            ->as('freeswitch.')
            ->middleware('freeswitch.enabled')
            ->group(function () {
                Route::get('/directory', [DirectoryController::class, 'show'])
                    ->name('directory.show');
            });

        Route::prefix('settings')
            ->as('settings.')
            ->group(function () {
                Route::get('/preload', [SettingsController::class, 'preload'])
                    ->name('preload');
            });

        /**
         * --------------------------------------------------------
         * Protected v1 API
         * --------------------------------------------------------
         */

        Route::middleware('auth:sanctum')
            ->group(function () {
                Route::prefix('auth')
                    ->as('auth.')
                    ->group(function () {
                Route::get('/me', [AuthController::class, 'me'])
                            ->name('me');
                        Route::post('/logout', [AuthController::class, 'logout'])
                            ->name('logout');
                        Route::get('/session/me', [AuthController::class, 'sessionUser'])
                            ->middleware('web')
                            ->name('session.me');
                        Route::post('/session/logout', [AuthController::class, 'sessionLogout'])
                            ->middleware('web')
                            ->name('session.logout');
                    });

                Route::prefix('user')
                    ->as('user.')
                    ->middleware('resolve.tenant')
                    ->group(function () {
                        Route::get('/tenants', [TenantController::class, 'index'])
                            ->name('tenants.index');

                        Route::get('/tenant', [TenantController::class, 'show'])
                            ->middleware('require.tenant')
                            ->name('tenant.show');

                        Route::post('/tenant/switch', [TenantController::class, 'switchTenant'])
                            ->name('tenant.switch');
                    });

                /**
                 * ------------------------------------------------
                 * Users
                 * ------------------------------------------------
                 */

                Route::prefix('users')
                    ->as('users.')
                    ->group(function () {

                        Route::get('/', [UserController::class, 'index'])
                            ->middleware('permission:users.view')
                            ->name('index');

                        Route::get('/{user}', [UserController::class, 'show'])
                            ->middleware('permission:users.view')
                            ->name('show');

                        Route::post('/', [UserController::class, 'store'])
                            ->middleware('permission:users.create')
                            ->name('store');

                        Route::put('/{user}', [UserController::class, 'update'])
                            ->middleware('permission:users.edit')
                            ->name('update');

                        Route::patch('/{user}', [UserController::class, 'update'])
                            ->middleware('permission:users.edit')
                            ->name('patch');

                        Route::delete('/{user}', [UserController::class, 'destroy'])
                            ->middleware('permission:users.delete')
                            ->name('destroy');
                    });

                Route::prefix('roles')
                    ->as('roles.')
                    ->group(function () {
                        Route::get('/', [RoleController::class, 'index'])
                            ->middleware('permission:roles.view')
                            ->name('index');
                        Route::post('/', [RoleController::class, 'store'])
                            ->middleware('permission:roles.create')
                            ->name('store');
                        Route::put('/{role}', [RoleController::class, 'update'])
                            ->middleware('permission:roles.edit')
                            ->name('update');
                        Route::patch('/{role}', [RoleController::class, 'update'])
                            ->middleware('permission:roles.edit')
                            ->name('patch');
                    });

                Route::prefix('permissions')
                    ->as('permissions.')
                    ->group(function () {
                        Route::get('/', [PermissionController::class, 'index'])
                            ->middleware('permission:permissions.view')
                            ->name('index');
                        Route::post('/', [PermissionController::class, 'store'])
                            ->middleware('permission:permissions.create')
                            ->name('store');
                        Route::put('/{permission}', [PermissionController::class, 'update'])
                            ->middleware('permission:permissions.edit')
                            ->name('update');
                        Route::patch('/{permission}', [PermissionController::class, 'update'])
                            ->middleware('permission:permissions.edit')
                            ->name('patch');
                    });

                /**
                 * ------------------------------------------------
                 * Dashboard / System
                 * ------------------------------------------------
                 */

                Route::get('/stats', [StatsController::class, 'index'])
                    ->name('stats');

                Route::get('/activity', [ActivityController::class, 'index'])
                    ->middleware('permission:activity.view')
                    ->name('activity');

                Route::get('/meta', [MetaController::class, 'index'])
                    ->name('meta');
                Route::get('/meta/bootstrap', [MetaController::class, 'bootstrap'])
                    ->name('meta.bootstrap');
                Route::get('/meta/rbac', [MetaController::class, 'rbac'])
                    ->name('meta.rbac');

                Route::get('/system/health', [MonitoringHealthController::class, 'readiness'])
                    ->middleware('permission:system.monitoring')
                    ->name('system.health');

                /**
                 * ------------------------------------------------
                 * Settings
                 * ------------------------------------------------
                 */

                Route::prefix('settings')
                    ->as('settings.')
                    ->group(function () {
                        Route::get('/', [SettingsController::class, 'index'])
                            ->middleware('permission:settings.view')
                            ->name('index');

                        Route::get('/effective', [SettingsController::class, 'effective'])
                            ->middleware('permission:settings.view')
                            ->name('effective');

                        Route::post('/', [SettingsController::class, 'store'])
                            ->middleware('permission:settings.edit')
                            ->name('store');

                        Route::put('/{setting}', [SettingsController::class, 'update'])
                            ->middleware('permission:settings.edit')
                            ->name('update');

                        Route::patch('/{setting}', [SettingsController::class, 'update'])
                            ->middleware('permission:settings.edit')
                            ->name('patch');

                        Route::delete('/{setting}', [SettingsController::class, 'destroy'])
                            ->middleware('permission:settings.edit')
                            ->name('destroy');
                    });

                /**
                 * ------------------------------------------------
                 * API Tokens
                 * ------------------------------------------------
                 */

                Route::prefix('tokens')
                    ->as('tokens.')
                    ->group(function () {

                        Route::get('/', [TokenController::class, 'index'])
                            ->middleware('permission:tokens.view')
                            ->name('index');

                        Route::post('/', [TokenController::class, 'store'])
                            ->middleware('permission:tokens.create')
                            ->name('store');

                        Route::delete('/{id}', [TokenController::class, 'destroy'])
                            ->middleware('permission:tokens.delete')
                            ->name('destroy');
                    });

                /**
                 * ------------------------------------------------
                 * Localization / Translations
                 * ------------------------------------------------
                 */

                Route::prefix('translations')
                    ->as('translations.')
                    ->group(function () {
                        Route::get('/manage', [TranslationManagementController::class, 'index'])
                            ->middleware('permission:translations.view')
                            ->name('manage.index');

                        Route::post('/manage', [TranslationManagementController::class, 'store'])
                            ->middleware('permission:translations.create')
                            ->name('manage.store');

                        Route::put('/manage/{translation}', [TranslationManagementController::class, 'update'])
                            ->middleware('permission:translations.edit')
                            ->name('manage.update');

                        Route::delete('/manage/{translation}', [TranslationManagementController::class, 'destroy'])
                            ->middleware('permission:translations.delete')
                            ->name('manage.destroy');
                    });

                /**
                 * ------------------------------------------------
                 * Realtime Debug
                 * ------------------------------------------------
                 */
                Route::prefix('realtime')
                    ->as('realtime.')
                    ->group(function () {
                        Route::post('/notify', [RealtimeController::class, 'notify'])
                            ->name('notify');
                    });

                /**
                 * ---------------------------------------------------------
                 * Notifications
                 * ---------------------------------------------------------
                 */
                Route::prefix('notifications')
                    ->as('notifications.')
                    ->group(function (): void {
                    Route::get('/', [NotificationController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:notifications.view');

                    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])
                        ->name('unread-count')
                        ->middleware('permission:notifications.view');

                    Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])
                        ->name('view')
                        ->middleware('permission:notifications.view');

                    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead'])
                        ->name('view-all')
                        ->middleware('permission:notifications.view');

                    Route::delete('/{notification}', [NotificationController::class, 'destroy'])
                        ->name('delete')
                        ->middleware('permission:notifications.delete');

                    Route::get('/preferences', [NotificationController::class, 'preferences'])
                        ->name('preferences')
                        ->middleware('permission:notifications.view');

                    Route::patch('/preferences', [NotificationController::class, 'updatePreferences'])
                        ->name('preferences.update')
                        ->middleware('permission:notifications.view');
                });

                /**
                 * ------------------------------------------------
                 * Chat (read-only foundation)
                 * ------------------------------------------------
                 */
                Route::prefix('chat')
                    ->as('chat.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/conversations', [ChatConversationController::class, 'index'])
                        ->name('conversations.index')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::post('/conversations/direct', [ChatConversationController::class, 'storeDirect'])
                        ->name('conversations.direct.store')
                        ->middleware('permission:chat.create|chat.conversations.create');

                    Route::post('/conversations/group', [ChatConversationController::class, 'storeGroup'])
                        ->name('conversations.group.store')
                        ->middleware('permission:chat.create|chat.conversations.create');

                    Route::post('/conversations/{conversation}/create-private-group', [ChatConversationController::class, 'createPrivateGroupFromDirect'])
                        ->name('conversations.private-group-from-direct.store')
                        ->middleware('permission:chat.create|chat.conversations.create');

                    Route::get('/conversations/{conversation}', [ChatConversationController::class, 'show'])
                        ->name('conversations.show')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::get('/conversations/{conversation}/messages', [ChatConversationController::class, 'messages'])
                        ->name('conversations.messages.index')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::get('/conversations/{conversation}/messages/search', [ChatMessageController::class, 'search'])
                        ->name('conversations.messages.search')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::post('/conversations/{conversation}/call-started', [ChatMessageController::class, 'storeCallStarted'])
                        ->name('conversations.call-started.store')
                        ->middleware('permission:chat.view|chat.conversations.view|call_control.view');

                    Route::get('/conversations/{conversation}/webhook-deliveries', [ChatConversationController::class, 'webhookDeliveries'])
                        ->name('conversations.webhook-deliveries.index')
                        ->middleware('permission:chat.webhooks.view|chat.webhooks.manage|chat.admin.view_metadata');

                    Route::post('/conversations/{conversation}/leave', [ChatConversationController::class, 'leave'])
                        ->name('conversations.leave')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::patch('/conversations/{conversation}/close', [ChatConversationController::class, 'close'])
                        ->name('conversations.close')
                        ->middleware('permission:chat.conversations.close|chat.admin.close_conversations');

                    Route::patch('/conversations/{conversation}/archive', [ChatConversationController::class, 'archive'])
                        ->name('conversations.archive')
                        ->middleware('permission:chat.conversations.archive');

                    Route::post('/devices', [ChatDeviceController::class, 'upsert'])
                        ->name('devices.upsert')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::patch('/conversations/{conversation}/read', [ChatReadStateController::class, 'markConversationRead'])
                        ->name('conversations.read')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::patch('/messages/{message}/read', [ChatReadStateController::class, 'markMessageRead'])
                        ->name('messages.read')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::get('/conversations/{conversation}/participants', [ChatConversationParticipantController::class, 'index'])
                        ->name('conversations.participants.index')
                        ->middleware('permission:chat.participants.view');

                    Route::post('/conversations/{conversation}/participants', [ChatConversationParticipantController::class, 'store'])
                        ->name('conversations.participants.store')
                        ->middleware('permission:chat.participants.add');

                    Route::delete('/conversations/{conversation}/participants/{participantUser}', [ChatConversationParticipantController::class, 'destroy'])
                        ->name('conversations.participants.destroy')
                        ->middleware('permission:chat.participants.remove');

                    Route::patch('/conversations/{conversation}/participants/{participantUser}/access', [ChatConversationParticipantController::class, 'updateAccess'])
                        ->name('conversations.participants.access.update')
                        ->middleware('permission:chat.participants.manage|chat.admin.moderate');

                    Route::patch('/conversations/{conversation}/participants/{participantUser}/block', [ChatConversationParticipantController::class, 'block'])
                        ->name('conversations.participants.block')
                        ->middleware('permission:chat.participants.manage|chat.admin.moderate');

                    Route::patch('/conversations/{conversation}/participants/{participantUser}/unblock', [ChatConversationParticipantController::class, 'unblock'])
                        ->name('conversations.participants.unblock')
                        ->middleware('permission:chat.participants.manage|chat.admin.moderate');

                    Route::patch('/conversations/{conversation}/participants/{participantUser}/capabilities', [ChatConversationParticipantController::class, 'updateCapabilities'])
                        ->name('conversations.participants.capabilities.update')
                        ->middleware('permission:chat.participants.manage|chat.admin.moderate');

                    Route::post('/conversations/{conversation}/messages', [ChatMessageController::class, 'store'])
                        ->name('messages.store')
                        ->middleware('throttle:chat-message-send')
                        ->middleware('permission:chat.send');

                    Route::patch('/messages/{message}', [ChatMessageController::class, 'update'])
                        ->name('messages.update')
                        ->middleware('permission:chat.edit|chat.admin.moderate');

                    Route::delete('/messages/{message}', [ChatMessageController::class, 'destroy'])
                        ->name('messages.destroy')
                        ->middleware('permission:chat.delete|chat.admin.delete_messages|chat.admin.moderate');

                    Route::post('/messages/{message}/attachments', [ChatAttachmentController::class, 'store'])
                        ->name('attachments.store')
                        ->middleware('throttle:chat-attachments')
                        ->middleware('permission:chat.attachments.upload');

                    Route::get('/attachments/{attachment}/download', [ChatAttachmentController::class, 'download'])
                        ->name('attachments.download')
                        ->middleware('permission:chat.attachments.download|chat.attachments.view|chat.view|chat.conversations.view');

                    Route::delete('/attachments/{attachment}', [ChatAttachmentController::class, 'destroy'])
                        ->name('attachments.destroy')
                        ->middleware('permission:chat.attachments.delete|chat.admin.moderate');

                    Route::post('/conversations/{conversation}/typing/start', [ChatTypingController::class, 'start'])
                        ->name('conversations.typing.start')
                        ->middleware('throttle:chat-typing')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::post('/conversations/{conversation}/typing/stop', [ChatTypingController::class, 'stop'])
                        ->name('conversations.typing.stop')
                        ->middleware('throttle:chat-typing')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::post('/conversations/{conversation}/presence/leave', [ChatPresenceController::class, 'leave'])
                        ->name('conversations.presence.leave')
                        ->middleware('permission:chat.view|chat.conversations.view');

                    Route::get('/webhook-endpoints', [ChatWebhookEndpointController::class, 'index'])
                        ->name('webhook-endpoints.index')
                        ->middleware('throttle:chat-webhook-management')
                        ->middleware('permission:chat.webhooks.view|chat.webhooks.manage|chat.admin.view_metadata');

                    Route::post('/webhook-endpoints', [ChatWebhookEndpointController::class, 'store'])
                        ->name('webhook-endpoints.store')
                        ->middleware('throttle:chat-webhook-management')
                        ->middleware('permission:chat.webhooks.create|chat.webhooks.manage|chat.admin.moderate');

                    Route::patch('/webhook-endpoints/{endpoint}', [ChatWebhookEndpointController::class, 'update'])
                        ->name('webhook-endpoints.update')
                        ->middleware('throttle:chat-webhook-management')
                        ->middleware('permission:chat.webhooks.edit|chat.webhooks.manage|chat.admin.moderate');

                    Route::delete('/webhook-endpoints/{endpoint}', [ChatWebhookEndpointController::class, 'destroy'])
                        ->name('webhook-endpoints.destroy')
                        ->middleware('throttle:chat-webhook-management')
                        ->middleware('permission:chat.webhooks.delete|chat.webhooks.manage|chat.admin.moderate');

                    Route::post('/webhook-endpoints/{endpoint}/rotate-secret', [ChatWebhookEndpointController::class, 'rotateSecret'])
                        ->name('webhook-endpoints.rotate-secret')
                        ->middleware('throttle:chat-webhook-management')
                        ->middleware('permission:chat.webhooks.manage|chat.admin.moderate');
                });

                Route::prefix('contacts')
                    ->as('contacts.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [ContactController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:contacts.view');

                    Route::get('/search', [ContactController::class, 'search'])
                        ->name('search')
                        ->middleware('permission:contacts.view');

                    Route::get('/lookup-phone', [ContactController::class, 'lookupPhone'])
                        ->name('lookup-phone')
                        ->middleware('permission:contacts.view');

                    Route::post('/', [ContactController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:contacts.create');

                    Route::get('/export', ContactExportController::class)
                        ->name('export')
                        ->middleware('permission:contacts.export');

                    Route::post('/import/validate', [ContactImportController::class, 'validateImport'])
                        ->name('import.validate')
                        ->middleware('permission:contacts.import');

                    Route::post('/import', [ContactImportController::class, 'import'])
                        ->name('import')
                        ->middleware('permission:contacts.import');

                    Route::get('/{contact}', [ContactController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:contacts.view');

                    Route::put('/{contact}', [ContactController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:contacts.update');

                    Route::patch('/{contact}', [ContactController::class, 'update'])
                        ->name('patch')
                        ->middleware('permission:contacts.update');

                    Route::delete('/{contact}', [ContactController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:contacts.delete');
                });

                Route::prefix('contact-tags')
                    ->as('contact-tags.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [ContactTagController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:contacts.view');

                    Route::post('/', [ContactTagController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:contacts.manage_tags');

                    Route::put('/{tag}', [ContactTagController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:contacts.manage_tags');

                    Route::delete('/{tag}', [ContactTagController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:contacts.manage_tags');
                });

                Route::prefix('extensions')
                    ->as('extensions.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [ExtensionController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:extensions.view');

                    Route::get('/assignment-options', [ExtensionController::class, 'assignmentOptions'])
                        ->name('assignment-options')
                        ->middleware('permission:extensions.view');

                    Route::post('/', [ExtensionController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:extensions.create');

                    Route::get('/{extension}', [ExtensionController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:extensions.view');

                    Route::get('/{extension}/sip-profile', [ExtensionController::class, 'sipProfile'])
                        ->name('sip-profile')
                        ->middleware('permission:call_control.view');

                    Route::put('/{extension}', [ExtensionController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:extensions.update');

                    Route::patch('/{extension}', [ExtensionController::class, 'update'])
                        ->name('patch')
                        ->middleware('permission:extensions.update');

                    Route::post('/{extension}/rotate-credentials', [ExtensionController::class, 'rotateCredentials'])
                        ->name('rotate-credentials')
                        ->middleware('permission:extensions.manage_credentials');

                    Route::delete('/{extension}', [ExtensionController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:extensions.delete');
                });

                Route::prefix('ring-groups')
                    ->as('ring-groups.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [RingGroupController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:ring_groups.view');
                    Route::get('/options', [RingGroupController::class, 'options'])
                        ->name('options')
                        ->middleware('permission:ring_groups.view');
                    Route::post('/', [RingGroupController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:ring_groups.create');
                    Route::get('/{ringGroup}', [RingGroupController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:ring_groups.view');
                    Route::put('/{ringGroup}', [RingGroupController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:ring_groups.update');
                    Route::delete('/{ringGroup}', [RingGroupController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:ring_groups.delete');
                    Route::get('/{ringGroup}/members', [RingGroupMemberController::class, 'index'])
                        ->name('members.index')
                        ->middleware('permission:ring_groups.view');
                    Route::post('/{ringGroup}/members', [RingGroupMemberController::class, 'store'])
                        ->name('members.store')
                        ->middleware('permission:ring_groups.manage_members');
                    Route::put('/{ringGroup}/members/{member}', [RingGroupMemberController::class, 'update'])
                        ->name('members.update')
                        ->middleware('permission:ring_groups.manage_members');
                    Route::delete('/{ringGroup}/members/{member}', [RingGroupMemberController::class, 'destroy'])
                        ->name('members.destroy')
                        ->middleware('permission:ring_groups.manage_members');
                    Route::post('/{ringGroup}/test-route', [RingGroupController::class, 'testRoute'])
                        ->name('test-route')
                        ->middleware('permission:ring_groups.test_route');
                });

                Route::prefix('call-queues')
                    ->as('call-queues.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [CallQueueController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:call_queues.view');
                    Route::get('/options', [CallQueueController::class, 'options'])
                        ->name('options')
                        ->middleware('permission:call_queues.view');
                    Route::post('/', [CallQueueController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:call_queues.create');
                    Route::get('/{callQueue}', [CallQueueController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:call_queues.view');
                    Route::put('/{callQueue}', [CallQueueController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:call_queues.update');
                    Route::delete('/{callQueue}', [CallQueueController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:call_queues.delete');
                    Route::get('/{callQueue}/members', [CallQueueMemberController::class, 'index'])
                        ->name('members.index')
                        ->middleware('permission:call_queues.view');
                    Route::post('/{callQueue}/members', [CallQueueMemberController::class, 'store'])
                        ->name('members.store')
                        ->middleware('permission:call_queues.manage_members');
                    Route::put('/{callQueue}/members/{member}', [CallQueueMemberController::class, 'update'])
                        ->name('members.update')
                        ->middleware('permission:call_queues.manage_members');
                    Route::delete('/{callQueue}/members/{member}', [CallQueueMemberController::class, 'destroy'])
                        ->name('members.destroy')
                        ->middleware('permission:call_queues.manage_members');
                    Route::post('/{callQueue}/members/{member}/pause', [CallQueueMemberController::class, 'pause'])
                        ->name('members.pause')
                        ->middleware('permission:call_queues.pause_members');
                    Route::post('/{callQueue}/members/{member}/resume', [CallQueueMemberController::class, 'resume'])
                        ->name('members.resume')
                        ->middleware('permission:call_queues.pause_members');
                    Route::post('/{callQueue}/test-route', [CallQueueController::class, 'testRoute'])
                        ->name('test-route')
                        ->middleware('permission:call_queues.test_route');
                });

                Route::prefix('ivr-menus')
                    ->as('ivr-menus.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [IvrMenuController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:ivr.view');
                    Route::get('/options', [IvrMenuController::class, 'options'])
                        ->name('options')
                        ->middleware('permission:ivr.view');
                    Route::post('/', [IvrMenuController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:ivr.create');
                    Route::get('/{ivrMenu}', [IvrMenuController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:ivr.view');
                    Route::put('/{ivrMenu}', [IvrMenuController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:ivr.update');
                    Route::delete('/{ivrMenu}', [IvrMenuController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:ivr.delete');
                    Route::get('/{ivrMenu}/options', [IvrOptionController::class, 'index'])
                        ->name('options.index')
                        ->middleware('permission:ivr.view');
                    Route::post('/{ivrMenu}/options', [IvrOptionController::class, 'store'])
                        ->name('options.store')
                        ->middleware('permission:ivr.manage_options');
                    Route::put('/{ivrMenu}/options/{option}', [IvrOptionController::class, 'update'])
                        ->name('options.update')
                        ->middleware('permission:ivr.manage_options');
                    Route::delete('/{ivrMenu}/options/{option}', [IvrOptionController::class, 'destroy'])
                        ->name('options.destroy')
                        ->middleware('permission:ivr.manage_options');
                    Route::post('/{ivrMenu}/test-route', [IvrMenuController::class, 'testRoute'])
                        ->name('test-route')
                        ->middleware('permission:ivr.test_route');
                });

                Route::prefix('phone-numbers')
                    ->as('phone-numbers.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [PhoneNumberController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:phone_numbers.view');

                    Route::get('/assignment-options', [PhoneNumberController::class, 'assignmentOptions'])
                        ->name('assignment-options')
                        ->middleware('permission:phone_numbers.view');

                    Route::post('/', [PhoneNumberController::class, 'store'])
                        ->name('store')
                        ->middleware('permission:phone_numbers.create');

                    Route::get('/{phoneNumber}', [PhoneNumberController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:phone_numbers.view');

                    Route::put('/{phoneNumber}', [PhoneNumberController::class, 'update'])
                        ->name('update')
                        ->middleware('permission:phone_numbers.update');

                    Route::patch('/{phoneNumber}', [PhoneNumberController::class, 'update'])
                        ->name('patch')
                        ->middleware('permission:phone_numbers.update');

                    Route::delete('/{phoneNumber}', [PhoneNumberController::class, 'destroy'])
                        ->name('destroy')
                        ->middleware('permission:phone_numbers.delete');

                    Route::post('/{phoneNumber}/assign', [PhoneNumberController::class, 'assign'])
                        ->name('assign')
                        ->middleware('permission:phone_numbers.assign');

                    Route::post('/{phoneNumber}/unassign', [PhoneNumberController::class, 'unassign'])
                        ->name('unassign')
                        ->middleware('permission:phone_numbers.assign');

                    Route::post('/{phoneNumber}/set-primary', [PhoneNumberController::class, 'setPrimary'])
                        ->name('set-primary')
                        ->middleware('permission:phone_numbers.set_primary');

                    Route::post('/{phoneNumber}/activate', [PhoneNumberController::class, 'activate'])
                        ->name('activate')
                        ->middleware('permission:phone_numbers.provision');

                    Route::post('/{phoneNumber}/suspend', [PhoneNumberController::class, 'suspend'])
                        ->name('suspend')
                        ->middleware('permission:phone_numbers.provision');

                    Route::post('/{phoneNumber}/release', [PhoneNumberController::class, 'release'])
                        ->name('release')
                        ->middleware('permission:phone_numbers.release');
                });

                Route::prefix('call-logs')
                    ->as('call-logs.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/', [CallLogController::class, 'index'])
                        ->name('index')
                        ->middleware('permission:call_logs.view');

                    Route::get('/export', [CallLogController::class, 'export'])
                        ->name('export')
                        ->middleware('permission:call_logs.export');

                    Route::get('/statistics', [CallLogController::class, 'statistics'])
                        ->name('statistics')
                        ->middleware('permission:call_logs.view_statistics');

                    Route::get('/filter-options', [CallLogController::class, 'filterOptions'])
                        ->name('filter-options')
                        ->middleware('permission:call_logs.view');

                    Route::get('/{callLog}', [CallLogController::class, 'show'])
                        ->name('show')
                        ->middleware('permission:call_logs.view');

                    Route::get('/{callLog}/events', [CallLogController::class, 'events'])
                        ->name('events')
                        ->middleware('permission:call_logs.view');
                });

                Route::prefix('users')
                    ->as('tenant-users.')
                    ->middleware('resolve.tenant')
                    ->group(function (): void {
                    Route::get('/{user}/phone-numbers', [PhoneNumberController::class, 'userPhoneNumbers'])
                        ->name('phone-numbers')
                        ->middleware('permission:phone_numbers.view');

                    Route::get('/{user}/primary-did', [PhoneNumberController::class, 'userPrimaryDid'])
                        ->name('primary-did')
                        ->middleware('permission:phone_numbers.view');
                });



            });
    });
