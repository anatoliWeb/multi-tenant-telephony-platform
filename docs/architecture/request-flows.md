# Request Flows

## Purpose

This document records the real request flows that exist in the repository today.

## Laravel API Request

### Example: tenant context bootstrap

`backend/routes/api.php`
-> `auth:sanctum`
-> `resolve.tenant`
-> `App\Http\Controllers\Api\User\TenantController`
-> `App\Services\Tenancy\TenantBootstrapService`
-> `App\Services\Rbac\PermissionCacheService`
-> tenant context payload with `platform_permissions`, `tenant_permissions`, and `current_tenant_id`

This flow is read-only. Tenant/demo creation is intentionally excluded from runtime bootstrap.

### Example: public frontend settings preload

`backend/routes/api.php`
-> public `/api/v1/settings/preload`
-> `App\Http\Controllers\Api\SettingsController::preload()`
-> `App\Services\Settings\SettingsService::preloadPublicFrontend()`
-> standardized JSON success response with public frontend-only settings

This flow is intentionally unauthenticated because Angular requests it before
session restoration completes. Only safe public settings may be returned.

### Example: chat message send

`backend/routes/api.php`
-> `auth:sanctum`
-> `permission:chat.send`
-> `App\Http\Requests\Api\SendChatMessageRequest`
-> `App\Http\Controllers\Api\V1\Chat\ChatMessageController::store()`
-> `App\Services\Chat\ChatMessageService::sendMessage()`
-> `App\Models\Message` and related conversation queries
-> `App\Http\Resources\Chat\ChatMessageResource`
-> `BaseController::successResponse()`

### Example: session login

`backend/routes/api.php`
-> `web`
-> `throttle:auth-login`
-> `App\Http\Requests\Api\AuthSessionLoginRequest`
-> `App\Http\Controllers\Api\AuthController::sessionLogin()`
-> `App\Services\AuthService::sessionLogin()`
-> standardized JSON success response

## Angular Request

### Example: authentication

`frontend/src/app/features/auth/pages/login/login-page.component.ts`
-> `AuthRuntimeService`
-> `AuthApiService`
-> `ApiClientService`
-> `auth-session.interceptor.ts`
-> `HttpClient`
-> Laravel API

### Example: chat workspace

`frontend/src/app/features/chat/services/chat-state.service.ts`
-> `ChatApiService`
-> `ApiClientService`
-> `auth-session.interceptor.ts`
-> `HttpClient`
-> Laravel API

`ChatStateService` also coordinates device registration, presence joins, typing events, and realtime subscriptions after the API request succeeds.

### Example: contacts list

`frontend/src/app/features/contacts/services/contacts-state.service.ts`
-> `ContactsApiService`
-> `ApiClientService`
-> tenant/auth interceptors
-> `HttpClient`
-> Laravel API

The contacts state service also clears stale tenant-scoped state when the active tenant changes.

If no tenant is active, Angular must stop before tenant feature requests and surface tenant-selection state instead.

### Example: extensions workspace

`frontend/src/app/features/extensions/services/extensions-state.service.ts`
-> `ExtensionsApiService`
-> `ApiClientService`
-> tenant/auth interceptors
-> `HttpClient`
-> Laravel API

The extensions state service clears tenant-scoped list, detail, filters, and one-time credential state when the active tenant changes.

### Example: tenant selector bootstrap

`frontend/src/app/layout/components/topbar/topbar.component.ts`
-> `TenantContextService`
-> `/api/v1/user/tenants`
-> `/api/v1/user/tenant`
-> tenant selector render state

The topbar may render Platform Admin tenant summaries before tenant selection,
but tenant feature modules must stay dormant until `current_tenant_id` exists.

## Vue Administration Request

### Example: login

`backend/resources/js/modules/auth/views/LoginView.vue`
-> `useAuthStore().login()`
-> `backend/resources/js/services/auth/auth.service.ts`
-> `backend/resources/js/services/api/client.ts`
-> `backend/resources/js/services/api/http.ts`
-> `backend/resources/js/services/api/interceptors.ts`
-> Laravel API

### Example: dashboard data

`backend/resources/js/modules/dashboard/pages/DashboardPage.vue`
-> `api.get('/v1/stats')`
-> `backend/resources/js/services/api/client.ts`
-> `backend/resources/js/services/api/http.ts`
-> Laravel API

Vue admin navigation stays platform-scoped. It may hydrate tenant selection state for support flows, but it must not use tenant permissions to decide platform navigation visibility.

### Example: platform support telephony page

`backend/resources/js/modules/tenant-support/pages/ContactsSupportPage.vue`
-> `tenant-support.service.ts`
-> `backend/resources/js/services/api/http.ts`
-> `/api/v1/contacts` with selected `X-Tenant-ID`
-> Laravel tenant-scoped API response

The same pattern is used for support views for extensions, phone numbers, and
call logs. Vue Admin must require explicit tenant selection before these
requests.

### Example: platform support IVR page

`backend/resources/js/modules/tenant-support/pages/IvrSupportPage.vue`
-> `tenant-support.service.ts`
-> `backend/resources/js/services/api/http.ts`
-> `/api/v1/ivr-menus` with selected `X-Tenant-ID`
-> Laravel tenant-scoped API response

The IVR support page is read-only and shows menu summaries, option counts, and
timeout or invalid-input routing summaries for the selected tenant only.

## Realtime Flow

### Example: chat message broadcast

`App\Services\Chat\ChatMessageService::sendMessage()`
-> `App\Events\Chat\ChatMessageCreated`
-> `PrivateChannel("chat.conversation.{id}")`
-> Reverb
-> Angular Echo client / Vue realtime client
-> frontend state update in `ChatStateService` or Vue realtime state

Channel authorization is enforced in `backend/routes/channels.php` through `ChatAccessService` and `ChatPresenceService`.

## Queue Flow

### Example: chat webhook delivery

`App\Services\Chat\ChatMessageService::sendMessage()`
-> `App\Jobs\Chat\DeliverChatWebhookJob`
-> Redis queue `webhooks`
-> queue worker or Horizon
-> outbound webhook HTTP delivery

### Other current queue examples

- `App\Jobs\LogActivityJob` -> Redis queue `activity` -> `ActivityService`.
- `App\Jobs\Notifications\CreateNotificationJob` -> Redis queue `notifications` -> notification creation and optional realtime broadcast.

## Telephony Application Flow

### Example: fake telephony call origination

`TenantContext`
-> `App\Services\Telephony\TelephonyService::originateCall()`
-> `App\Services\Telephony\TelephonyProviderRegistry`
-> configured `CallControlProvider`
-> normalized shared DTO result

The current implementation uses a deterministic fake provider. No real PBX transport, SIP signaling, or FreeSWITCH adapter is active in this phase.

Stage 14 introduces an optional local FreeSWITCH Docker profile for future integration work, but the runtime flow above still resolves through the fake provider by default.
The working runtime uses image defaults rather than a custom `/etc/freeswitch` bind mount, so `mod_xml_curl` may log `Binding has no url` until dynamic directory and dialplan integration is added later.

### Example: tenant-aware extension provisioning

`TenantContext`
-> `App\Http\Controllers\Api\V1\Extensions\ExtensionController`
-> `App\Services\Extensions\ExtensionService`
-> `App\Services\Extensions\ExtensionProvisioningService`
-> `App\Services\Telephony\TelephonyService`
-> configured `EndpointProvisioningProvider`
-> normalized provider state stored on the extension

Credential rotation is handled separately through `ExtensionCredentialService`, which returns plaintext once and stores only encrypted values.

### Example: fake telephony call log recording

`TenantContext`
-> `App\Services\Telephony\TelephonyService::originateCall()`
-> fake `CallControlProvider`
-> `App\Services\CallLogs\CallRecordingService::recordOriginatedCall()`
-> `App\Services\CallLogs\CallLogService`
-> `App\Services\CallLogs\CallEventService`
-> tenant-owned `CallLog` and `CallEvent` rows

Follow-up state changes use:

`TelephonyService::{answerCall|holdCall|resumeCall|hangupCall}()`
-> fake provider state transition
-> `CallRecordingService::recordStateTransition()`
-> `CallLifecycleService`
-> recomputed durations and final disposition

This flow is simulated only. No real SIP signaling, RTP, or FreeSWITCH event stream is active.

### Example: tenant-aware IVR dry-run routing

`TenantContext`
-> `App\Http\Controllers\Api\V1\Ivr\IvrMenuController::testRoute()`
-> `App\Http\Requests\Api\TestIvrMenuRouteRequest`
-> `App\Services\Ivr\IvrMenuService::testRoute()`
-> `App\Services\Ivr\IvrRoutingService::resolve()`
-> normalized dry-run route plan

This flow validates IVR configuration only. It does not play audio, place calls, or invoke a PBX adapter yet.

The softphone foundation now loads a tenant-scoped SIP profile, but it still
keeps registration disabled until the call-control layer is explicitly wired
to a real provider and safe tenant credentials can be provisioned.
Stage 15.2 adds a local-demo-only credential gate so the same flow can be
tested in development without exposing secrets outside the approved local
environment.

## Contacts Lookup Flow

### Example: tenant-scoped caller lookup

`TenantContext`
-> `App\Http\Controllers\Api\V1\Contacts\ContactController::lookupPhone()`
-> `App\Services\Contacts\PhoneNumberNormalizer`
-> `App\Services\Contacts\ContactQueryService`
-> tenant-scoped `ContactPhone` query
-> `ContactLookupResource`

This flow is intentionally lookup-only in the current slice and does not place or receive calls.

## Notes

- Current flows are still modular-monolith flows.
- The request/queue/realtime chain is documented here as it exists today, not as a future extraction target.

## Phone Numbers Flow

### Example: tenant-aware DID assignment

`TenantContext`
-> `App\Http\Controllers\Api\V1\PhoneNumbers\PhoneNumberController`
-> `App\Services\PhoneNumbers\PhoneNumberService`
-> `App\Services\PhoneNumbers\PhoneNumberAssignmentService`
-> tenant-scoped `PhoneNumber` write
-> `PhoneNumberResource`

### Example: inbound DID ownership lookup

`normalized incoming number`
-> `App\Services\Contacts\PhoneNumberNormalizer`
-> `App\Services\PhoneNumbers\InboundDidResolver`
-> tenant-owned `PhoneNumber`
-> assigned `User` if present

This flow does not resolve directly to an extension and does not place real calls yet.
