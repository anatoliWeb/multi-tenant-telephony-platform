# Request Flows

## Purpose

This document records the real request flows that exist in the repository today.

## Laravel API Request

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

## Notes

- Current flows are still modular-monolith flows.
- The request/queue/realtime chain is documented here as it exists today, not as a future extraction target.
