# OpenAPI Preparation

## Goals
- Prepare a stable inventory for future OpenAPI generator integration.
- Capture current auth/middleware/validation/resource contracts without changing API behavior.
- Identify known gaps before selecting a generator package.

## Current API foundations
- Pagination foundation: available (`meta.current_page`, `meta.last_page`, `meta.per_page`, `meta.total`).
- Filtering/sorting/search foundation: available on multiple list endpoints (notably chat message search and conversation lists).
- Validation standardization: API validation errors return unified envelope (`success=false`, `message`, `errors`).
- Response envelope baseline: success/error/meta contract via `BaseController` + `ApiResponse` + API exception rendering.

## Auth schemes
- `auth:sanctum` for protected `/api/v1/*`.
- Session auth endpoints:
  - `POST /api/v1/auth/session/login`
  - `GET /api/v1/auth/session/me`
  - `POST /api/v1/auth/session/logout`
- Token/Bearer endpoints:
  - `POST /api/v1/auth/login`
  - `POST /api/v1/auth/token`
  - `GET /api/v1/auth/me`
  - `POST /api/v1/auth/logout`
- External chat token scopes middleware:
  - `external.chat.scope:chat.external.messages.send`
- Rate limiters in route middleware:
  - `throttle:chat-message-send`
  - `throttle:chat-external-api`
  - `throttle:chat-webhook-management`
- Webhook security:
  - HMAC signature verification
  - timestamp tolerance
  - replay protection
  - secret rotation support

## Auth Endpoints

### Canonical runtime model
- Vue Admin runtime is session-first for browser login state (`/api/v1/auth/session/*`).
- Bearer token flow is supported for API-first clients (`/api/v1/auth/*` token/me/logout).
- Protected API endpoints generally require either:
  - `BearerAuth` (token), or
  - `SanctumSession` cookie (`laravel_session`), depending on client flow.

### Endpoint contract (actual)

| Method | Path | Auth required | Request shape | Response shape | Notes |
|---|---|---|---|---|---|
| POST | `/api/v1/auth/session/login` | no (`web` middleware) | `email`, `password`, `remember?` | success envelope with auth context (`user`, `permissions`, `roles`) | Session login for admin/browser flow |
| GET | `/api/v1/auth/session/me` | yes (`auth:sanctum` + `web`) | none | success envelope with auth context | Returns current session user context |
| POST | `/api/v1/auth/session/logout` | yes (`auth:sanctum` + `web`) | none | success envelope (`data: []`) | Destroys current session |
| POST | `/api/v1/auth/token` | no | `email`, `password` | success envelope with `data.token` + auth context | Alias endpoint for token issuance |
| POST | `/api/v1/auth/login` | no | `email`, `password` | success envelope with `data.token` + auth context | Canonical token login alias |
| GET | `/api/v1/auth/me` | yes (`auth:sanctum`) | none | success envelope with auth context | Token identity endpoint |
| POST | `/api/v1/auth/logout` | yes (`auth:sanctum`) | none | success envelope (`data: []`) | Revokes current bearer token |

### Auth context response
- Shared auth context payload:
  - `data.user`
  - `data.permissions` (effective, denied permissions excluded)
  - `data.roles`
- Token login additionally returns:
  - `data.token`

### Auth errors
- Validation errors: `422` standardized validation envelope:
  - `success=false`, `message="Validation failed"`, `errors={...}`
- Unauthenticated protected access: `401` standardized error envelope.
- Invalid token credentials: `401` with standardized error envelope.

### Swagger Try It Out notes
- Preferred for protected API calls: `BearerAuth` (paste token without `Bearer` prefix in UI).
- Session cookie auth is documented via `SanctumSession` as an alternative for session-first browser flow.
- Do not place real tokens/passwords in shared screenshots/examples.

## Response envelope
- Success:
  - `success: true`
  - `message: string`
  - `data: mixed`
  - `meta?: object`
- Error:
  - `success: false`
  - `message: string`
  - `errors: object|array`
  - `meta?: object`

## Common Response Envelope

### 1. Success response
```json
{
  "success": true,
  "message": "Request successful",
  "data": {},
  "meta": {}
}
```

### 2. List/paginated response
```json
{
  "success": true,
  "message": "Data fetched",
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 7,
    "per_page": 15,
    "total": 100
  }
}
```

### 3. Error response
```json
{
  "success": false,
  "message": "Request failed",
  "errors": {}
}
```

### 4. Validation error response
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": [
      "The field is required."
    ]
  }
}
```

### 5. Common HTTP error variants
- `401 Unauthenticated`:
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": []
}
```
- `403 Forbidden`:
```json
{
  "success": false,
  "message": "Forbidden",
  "errors": []
}
```
- `404 Not Found`:
```json
{
  "success": false,
  "message": "Resource not found",
  "errors": []
}
```
- `429 Too Many Requests`:
```json
{
  "success": false,
  "message": "Too Many Attempts.",
  "errors": []
}
```
- `422 Validation`:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": [
      "Validation message"
    ]
  }
}
```
- `500 Server Error`:
```json
{
  "success": false,
  "message": "Server error",
  "errors": []
}
```

OpenAPI schema candidates for this contract are registered in generated docs as:
- `ApiSuccessResponse`
- `ApiErrorResponse`
- `ValidationErrorResponse`
- `PaginatedResponse`
- `PaginationMeta`

## Validation Error Response Format
- HTTP status: `422`
- Contract shape:
  - `success: false`
  - `message: "Validation failed"` (current project contract)
  - `errors: object`
- `errors` keys:
  - form field names
  - dot-notation keys for nested payload fields
- `errors` values:
  - array of human-readable validation messages
- Source:
  - Laravel `FormRequest` / validator exceptions are normalized by API exception rendering in `bootstrap/app.php`.

### Example: missing required field
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

### Example: invalid enum/value
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "history_import_mode": [
      "The selected history import mode is invalid."
    ]
  }
}
```

### Example: nested field
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "metadata.from_at": [
      "The metadata.from at is not a valid date."
    ]
  }
}
```

## Error responses
- Validation: `422` + `message=Validation failed` + field-level `errors`.
- Unauthenticated: `401` + `message=Unauthenticated`.
- Forbidden: `403` + `message=Forbidden`.
- Not found: `404` + standardized JSON envelope.

## Pagination, Filtering, Sorting and Search

### Pagination
- Standard query params:
  - `page` (default Laravel paginator behavior)
  - `per_page` (commonly clamped to `1..100`)
- Common defaults by endpoint family:
  - conversations list: default `20`
  - conversation messages list: default `50`
  - message search: default `20`
  - activity/users/settings endpoints: typically `15`
- Paginated response contract:
  - `meta.current_page`
  - `meta.last_page`
  - `meta.per_page`
  - `meta.total`

### Filtering
- Project uses explicit query params (not generic `filter[field]`) on most endpoints.
- Common filters already used in production routes:
  - Chat conversations: `type`, `visibility`, `status`, `source`, `unread`
  - Chat message search: `type`, `sender_id`, `from`, `to`, `has_attachments`, `imported`
  - Activity: `action`, `user_id`, `subject_type` (or `model` alias), `date_from`, `date_to`
  - Users: `role`, `permission`
  - Settings/Translations: endpoint-specific filters such as `group`, `type`, `channel`, `locale`, `source`

### Sorting
- Existing sorting contract is endpoint-specific.
- Users endpoint supports:
  - `sort` (allowlist-enforced in service layer)
  - `direction` (`asc|desc`, normalized to safe defaults)
- Other list endpoints may currently use fixed server-side ordering (for example conversations by `last_message_at desc`).

### Search
- Search params in active use:
  - `search` (users/activity/settings/translations and similar list APIs)
  - `q` (chat message search endpoint)
- Behavior:
  - server-side partial matching (`LIKE`) on endpoint-defined fields
  - not all endpoints support search; support must be treated as per-endpoint contract.

### Query examples
- `/api/v1/chat/conversations?search=test&type=group&visibility=private&page=1&per_page=15`
- `/api/v1/users?search=admin&sort=name&direction=asc`
- `/api/v1/chat/conversations?unread=true`
- `/api/v1/chat/conversations/{conversation}/messages/search?q=onboarding&type=text&per_page=20`

## Chat Endpoints

### Conversations
| Method | Path | Auth | Permission | Request | Response | Notes |
|---|---|---|---|---|---|---|
| GET | `/api/v1/chat/conversations` | sanctum | `chat.view\|chat.conversations.view` | query: `page`, `per_page`, `search`, `type`, `visibility`, `status`, `source`, `unread` | paginated `ChatConversationResource` envelope | user-visible list with unread count |
| GET | `/api/v1/chat/conversations/{conversation}` | sanctum | `chat.view\|chat.conversations.view` | path param | `ChatConversationResource` envelope | non-visible conversations return 404 |
| POST | `/api/v1/chat/conversations/direct` | sanctum | `chat.create\|chat.conversations.create` | `CreateDirectConversationRequest` | `ChatConversationResource` (201) | direct chat create |
| POST | `/api/v1/chat/conversations/group` | sanctum | `chat.create\|chat.conversations.create` | `CreateGroupConversationRequest` | `ChatConversationResource` (201) | group chat create |
| POST | `/api/v1/chat/conversations/{conversation}/create-private-group` | sanctum | `chat.create\|chat.conversations.create` | `CreatePrivateGroupFromDirectRequest` | `ChatConversationResource` (201) | direct-to-private-group |
| POST | `/api/v1/chat/conversations/{conversation}/leave` | sanctum | `chat.view\|chat.conversations.view` | path param | `ChatParticipantResource` | participant leave flow |
| PATCH | `/api/v1/chat/conversations/{conversation}/close` | sanctum | `chat.conversations.close\|chat.admin.close_conversations` | path param | `ChatConversationResource` | conversation lifecycle |
| PATCH | `/api/v1/chat/conversations/{conversation}/archive` | sanctum | `chat.conversations.archive` | path param | `ChatConversationResource` | conversation lifecycle |

### Messages
| Method | Path | Auth | Permission | Request | Response | Notes |
|---|---|---|---|---|---|---|
| GET | `/api/v1/chat/conversations/{conversation}/messages` | sanctum | `chat.view\|chat.conversations.view` | query: `page`, `per_page`, `before_id` | paginated `ChatMessageResource` envelope | safe/admin-gated metadata |
| POST | `/api/v1/chat/conversations/{conversation}/messages` | sanctum | `chat.send` + `throttle:chat-message-send` | `SendChatMessageRequest` | `ChatMessageResource` (201) | send message endpoint |
| PATCH | `/api/v1/chat/messages/{message}` | sanctum | `chat.edit\|chat.admin.moderate` | `UpdateChatMessageRequest` | `ChatMessageResource` | edit message |
| DELETE | `/api/v1/chat/messages/{message}` | sanctum | `chat.delete\|chat.admin.delete_messages\|chat.admin.moderate` | path param | success envelope | soft delete status payload |
| GET | `/api/v1/chat/conversations/{conversation}/messages/search` | sanctum | `chat.view\|chat.conversations.view` | `SearchChatMessagesRequest` query (`q`, `type`, `sender_id`, `from`, `to`, `has_attachments`, `imported`, `per_page`) | paginated `ChatMessageResource` envelope | message search |

Delivery/read notes:
- Delivery/read fields are exposed as safe summary fields only.
- Admin-only metadata expansion is controlled by `chat.admin.view_metadata` gate.

### Participants
| Method | Path | Auth | Permission | Response | Notes |
|---|---|---|---|---|---|
| GET | `/api/v1/chat/conversations/{conversation}/participants` | sanctum | `chat.participants.view` | `ChatParticipantResource[]` | list participants |
| POST | `/api/v1/chat/conversations/{conversation}/participants` | sanctum | `chat.participants.add` | `ChatParticipantResource` | add participant |
| DELETE | `/api/v1/chat/conversations/{conversation}/participants/{participantUser}` | sanctum | `chat.participants.remove` | success envelope | remove participant |
| PATCH | `/api/v1/chat/conversations/{conversation}/participants/{participantUser}/access` | sanctum | `chat.participants.manage\|chat.admin.moderate` | `ChatParticipantResource` | full/read_only/hidden access state |
| PATCH | `/api/v1/chat/conversations/{conversation}/participants/{participantUser}/block` | sanctum | `chat.participants.manage\|chat.admin.moderate` | `ChatParticipantResource` | block participant |
| PATCH | `/api/v1/chat/conversations/{conversation}/participants/{participantUser}/unblock` | sanctum | `chat.participants.manage\|chat.admin.moderate` | `ChatParticipantResource` | unblock participant |
| PATCH | `/api/v1/chat/conversations/{conversation}/participants/{participantUser}/capabilities` | sanctum | `chat.participants.manage\|chat.admin.moderate` | `ChatParticipantResource` | capability toggles |

Safe payload note:
- Participant/admin payloads are sanitized; no secrets/tokens/device internals.

### Read and Device State
| Method | Path | Auth | Permission | Notes |
|---|---|---|---|---|
| POST | `/api/v1/chat/devices` | sanctum | `chat.view\|chat.conversations.view` | register/upsert chat device |
| PATCH | `/api/v1/chat/conversations/{conversation}/read` | sanctum | `chat.view\|chat.conversations.view` | mark conversation read |
| PATCH | `/api/v1/chat/messages/{message}/read` | sanctum | `chat.view\|chat.conversations.view` | mark message read |

### Attachments
| Method | Path | Auth | Permission | Request/Response | Notes |
|---|---|---|---|---|---|
| POST | `/api/v1/chat/messages/{message}/attachments` | sanctum | `chat.attachments.upload` | `UploadChatAttachmentRequest`, `ChatAttachmentResource` | upload attachment (multipart) |
| GET | `/api/v1/chat/attachments/{attachment}/download` | sanctum | `chat.attachments.download\|chat.attachments.view\|chat.view\|chat.conversations.view` | file response | download |
| DELETE | `/api/v1/chat/attachments/{attachment}` | sanctum | `chat.attachments.delete\|chat.admin.moderate` | success envelope | delete attachment |

Safe fields:
- only display-safe attachment fields are returned (`id`, `original_name`, `mime_type`, `size`, `status`), no disk/path/checksum.

### Realtime Helper Endpoints
| Method | Path | Auth | Permission | Notes |
|---|---|---|---|---|
| POST | `/api/v1/chat/conversations/{conversation}/typing/start` | sanctum | `chat.view\|chat.conversations.view` | typing indicator start |
| POST | `/api/v1/chat/conversations/{conversation}/typing/stop` | sanctum | `chat.view\|chat.conversations.view` | typing indicator stop |
| POST | `/api/v1/chat/conversations/{conversation}/presence/leave` | sanctum | `chat.view\|chat.conversations.view` | explicit presence cleanup |

### Admin Monitoring (Chat Scope)
| Method | Path | Auth | Permission | Notes |
|---|---|---|---|---|
| GET | `/api/v1/chat/conversations/{conversation}/webhook-deliveries` | sanctum | `chat.webhooks.view\|chat.webhooks.manage\|chat.admin.view_metadata` | paginated `ChatWebhookDeliverySummaryResource`, safe fields only |

Metadata gate note:
- Sensitive per-device/per-message metadata is only exposed behind admin metadata permission checks.

### Chat URL examples
- `/api/v1/chat/conversations?page=1&per_page=20&type=group&visibility=private&unread=true`
- `/api/v1/chat/conversations/123/messages?per_page=50&before_id=900`
- `/api/v1/chat/conversations/123/messages/search?q=invoice&type=text&has_attachments=true&per_page=20`

## Extension Endpoints

### Tenant Extensions
| Method | Path | Auth | Permission | Request | Response | Notes |
|---|---|---|---|---|---|---|
| GET | `/api/v1/extensions` | sanctum | `tenant.extensions.view` | `ListExtensionsRequest` query (`page`, `per_page`, `search`, `status`, `assigned`) | paginated `ExtensionResource` envelope | tenant-owned extension inventory |
| GET | `/api/v1/extensions/assignment-options` | sanctum | `tenant.extensions.view` | none | success envelope with `users[]` and `contacts[]` | tenant-safe assignee choices only |
| POST | `/api/v1/extensions` | sanctum | `tenant.extensions.create` | `StoreExtensionRequest` | `ExtensionResource` envelope with one-time `plain_secret` | creates extension, credentials, and fake-provider endpoint |
| GET | `/api/v1/extensions/{extension}` | sanctum | `tenant.extensions.view` | path param | `ExtensionResource` envelope | refreshes stored fake-provider state |
| PUT/PATCH | `/api/v1/extensions/{extension}` | sanctum | `tenant.extensions.update` | `UpdateExtensionRequest` | `ExtensionResource` envelope | updates assignment/status and reprovisions |
| POST | `/api/v1/extensions/{extension}/rotate-credentials` | sanctum | `tenant.extensions.manage_credentials` | path param | `ExtensionResource` envelope with one-time `plain_secret` | rotates SIP-style secret without exposing stored value later |
| DELETE | `/api/v1/extensions/{extension}` | sanctum | `tenant.extensions.delete` | path param | success envelope | removes tenant-owned extension and fake endpoint |

Extension safety rules:
- extension number uniqueness is enforced per tenant;
- tenant ownership is derived from `TenantContext`;
- assignment targets must belong to the active tenant;
- stored credentials never expose `secret_encrypted`;
- `plain_secret` appears only in create/rotate responses;
- fake-provider metadata is sanitized before persistence and responses.

## External API Endpoints

### External Message Sending
| Method | Path | Auth | Scope | Request | Response | Rate limit | Errors |
|---|---|---|---|---|---|---|---|
| POST | `/api/v1/chat/external/messages` | `ExternalChatToken` bearer (or internal sanctum user with external API permissions) | `chat.external.messages.send` via `external.chat.scope` middleware | `SendExternalChatMessageRequest` (`conversation_id`, `external_provider`, `external_message_id`, `body`, optional `type`, `metadata`, `sent_at`, `idempotency_key`) | success envelope with `ChatMessageResource` and `meta.idempotent` | `throttle:chat-external-api` | `401`, `403`, `422`, `429` |

Idempotency behavior:
- `external_provider` + `external_message_id` mapping is used to avoid duplicate creation.
- duplicate request returns success envelope with `meta.idempotent=true`.

### Incoming External Webhooks
| Method | Path | Auth | Security headers | Request | Response | Errors |
|---|---|---|---|---|---|---|
| POST | `/api/v1/chat/external/webhooks/{endpoint:uuid}` | public route | `X-Chat-Signature`, `X-Chat-Timestamp` (config-driven names) | `IncomingChatWebhookRequest` (`event=message.created`, `conversation_id`, `external_provider`, `external_message_id`, `body`, optional `type`, `sent_at`, `metadata`, `idempotency_key`) | success envelope with `ChatMessageResource` and `meta.idempotent` | `403` (signature/timestamp invalid), `409` (replay), `422` (validation/subscription), `429` (throttle) |

Incoming webhook security behavior:
- HMAC signature verification via `ChatWebhookSigningService`.
- Timestamp tolerance enforcement.
- Replay protection via `ChatWebhookReplayProtectionService`.
- Secret rotation compatibility (`current` + `previous` during grace window).

Idempotency behavior:
- duplicate provider/message mapping returns `meta.idempotent=true` without creating duplicate message rows.

### External API Security
- External tokens are validated by hash (`metadata.token_hash`), plain tokens are never stored.
- Token scopes are allowlist-enforced (`ExternalChatTokenService`).
- `chat.external.messages.send` is required for external message send route.
- External routes are rate-limited with `throttle:chat-external-api`.
- Responses/logs must not expose secrets:
  - no `token_hash`
  - no webhook secret values
  - no raw signature/token in payload or logs

## Webhook Endpoints

### Webhook Endpoint Management
| Method | Path | Auth | Permission | Request | Response | Rate limit | Notes |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/chat/webhook-endpoints` | sanctum | `chat.webhooks.view\|chat.webhooks.manage\|chat.admin.view_metadata` | query none | `ChatWebhookEndpointResource[]` envelope | `throttle:chat-webhook-management` | list endpoints, safe fields only |
| POST | `/api/v1/chat/webhook-endpoints` | sanctum | `chat.webhooks.create\|chat.webhooks.manage\|chat.admin.moderate` | `StoreChatWebhookEndpointRequest` | `ChatWebhookEndpointResource` envelope (includes one-time `plain_token`) | `throttle:chat-webhook-management` | create endpoint + secret + scoped external token metadata |
| PATCH | `/api/v1/chat/webhook-endpoints/{endpoint}` | sanctum | `chat.webhooks.edit\|chat.webhooks.manage\|chat.admin.moderate` | `UpdateChatWebhookEndpointRequest` | `ChatWebhookEndpointResource` envelope | `throttle:chat-webhook-management` | update URL/events/status/scopes |
| DELETE | `/api/v1/chat/webhook-endpoints/{endpoint}` | sanctum | `chat.webhooks.delete\|chat.webhooks.manage\|chat.admin.moderate` | path param | success envelope | `throttle:chat-webhook-management` | soft-delete endpoint |
| POST | `/api/v1/chat/webhook-endpoints/{endpoint}/rotate-secret` | sanctum | `chat.webhooks.manage\|chat.admin.moderate` | path param | success envelope (`rotated_at`, `previous_secret_expires_at`, one-time `plain_secret`) | `throttle:chat-webhook-management` | secret rotation with grace window |

No dedicated `show`/`enable`/`disable` route currently exists.
Enable/disable is controlled via update payload (`is_active` / `status`).

### Webhook Deliveries
| Method | Path | Auth | Permission | Response | Notes |
|---|---|---|---|---|---|
| GET | `/api/v1/chat/conversations/{conversation}/webhook-deliveries` | sanctum | `chat.webhooks.view\|chat.webhooks.manage\|chat.admin.view_metadata` | paginated `ChatWebhookDeliverySummaryResource` envelope | delivery status list for a conversation |

Current status values in lifecycle:
- `pending`
- `retrying`
- `sent`
- `failed`
- `cancelled`

Safe delivery summary fields:
- `id`, `event_type`, `status`, `attempts`, `max_attempts`
- `next_retry_at`, `last_status_code`, `error_summary`
- `endpoint_name`, `endpoint_url`
- timestamps (`created_at`, `updated_at`, `sent_at`, `failed_at`)

There is currently no public API route for manual retry of a delivery item; retries are handled by job/command flow.

### Incoming Webhooks
| Method | Path | Auth | Security | Request | Response | Notes |
|---|---|---|---|---|---|---|
| POST | `/api/v1/chat/external/webhooks/{endpoint:uuid}` | public (no sanctum) | `WebhookSignature` + `WebhookTimestamp`, HMAC + tolerance + replay protection | `IncomingChatWebhookRequest` | success envelope with `ChatMessageResource`, `meta.idempotent` | endpoint must be active and subscribed to event |

Incoming security behavior:
- required signature header: configured `chat.webhooks.signature_header` (default `X-Chat-Signature`)
- required timestamp header: configured `chat.webhooks.timestamp_header` (default `X-Chat-Timestamp`)
- timestamp tolerance enforced
- replay fingerprint cache check enforced
- current+previous secret verification during rotation grace window

Idempotency behavior:
- duplicate external message mapping returns idempotent response (`meta.idempotent=true`) without duplicate message creation.

### Outgoing Webhook Events
Actual subscribable events in endpoint create/update validation:
- `message.created`
- `message.updated`
- `message.deleted`
- `message.read`
- `message.device_read`
- `message.delivery.updated`
- `conversation.created`
- `participant.joined`
- `participant.left`
- `participant.blocked`
- `participant.unblocked`
- `participant.access_changed`
- `attachment.created`

Safe payload policy:
- no webhook secrets
- no token/token_hash
- no signature headers values in payload
- no raw internal request/response dumps
- no attachment storage internals (`disk/path/checksum`)

## Route inventory (critical groups)

| Route | Method | Controller | Auth | Permission / Scope | Request class | Resource/Response | Pagination | Filters/Sort/Search | Notes |
|---|---|---|---|---|---|---|---|---|---|
| `/api/v1/auth/login` | POST | `AuthController@token` | public | - | inline `Request` | envelope | no | no | token login |
| `/api/v1/auth/session/login` | POST | `AuthController@sessionLogin` | `web` | - | inline `Request` | envelope | no | no | session-first flow |
| `/api/v1/auth/me` | GET | `AuthController@me` | sanctum | - | - | `UserResource` envelope | no | no | protected identity |
| `/api/v1/meta/bootstrap` | GET | `MetaController@bootstrap` | sanctum | - | - | `MetaResource` envelope | no | no | lightweight bootstrap payload |
| `/api/v1/meta/rbac` | GET | `MetaController@rbac` | sanctum | - | - | `MetaResource` envelope | no | no | RBAC payload |
| `/api/v1/notifications/unread-count` | GET | `NotificationController@unreadCount` | sanctum | `notifications.view` | - | envelope | no | no | topbar counter |
| `/api/v1/chat/conversations` | GET | `ChatConversationController@index` | sanctum | `chat.view\|chat.conversations.view` | - | `ChatConversationResource` envelope | yes | filter/sort | chat list |
| `/api/v1/chat/conversations/{conversation}/messages` | GET | `ChatConversationController@messages` | sanctum | `chat.view\|chat.conversations.view` | - | `ChatMessageResource` envelope | yes | pagination | message list |
| `/api/v1/chat/conversations/{conversation}/messages` | POST | `ChatMessageController@store` | sanctum | `chat.send` + `throttle:chat-message-send` | `SendChatMessageRequest` | `ChatMessageResource` envelope | no | no | message send |
| `/api/v1/chat/messages/{message}` | PATCH | `ChatMessageController@update` | sanctum | `chat.edit\|chat.admin.moderate` | `UpdateChatMessageRequest` | `ChatMessageResource` envelope | no | no | message edit |
| `/api/v1/chat/messages/{message}` | DELETE | `ChatMessageController@destroy` | sanctum | `chat.delete\|chat.admin.delete_messages\|chat.admin.moderate` | - | envelope | no | no | soft-delete flow |
| `/api/v1/chat/external/messages` | POST | `ChatMessageController@storeExternal` | external token / user | `external.chat.scope:chat.external.messages.send` + `throttle:chat-external-api` | `SendExternalChatMessageRequest` | envelope + `meta.idempotent` | no | no | external API |
| `/api/v1/chat/external/webhooks/{endpoint:uuid}` | POST | `ChatIncomingWebhookController@handle` | public | `throttle:chat-external-api` | `IncomingChatWebhookRequest` | `ChatMessageResource` envelope | no | no | HMAC + replay protected |
| `/api/v1/chat/webhook-endpoints` | GET/POST | `ChatWebhookEndpointController@index/store` | sanctum | webhook permissions + management throttle | store: `StoreChatWebhookEndpointRequest` | `ChatWebhookEndpointResource` envelope | no | no | webhook management |
| `/api/v1/chat/conversations/{conversation}/webhook-deliveries` | GET | `ChatConversationController@webhookDeliveries` | sanctum | webhook view/manage/admin metadata | - | `ChatWebhookDeliverySummaryResource` envelope | yes | pagination | delivery status |

## Schema candidates
- `ApiSuccessResponse`
- `ApiErrorResponse`
- `ValidationErrorResponse`
- `PaginationMeta`
- `User`
- `Role`
- `Permission`
- `ChatConversation`
- `ChatMessage`
- `ChatAttachment`
- `ChatParticipant`
- `ChatDevice`
- `ChatReadState`
- `ChatWebhookEndpoint`
- `ChatWebhookDeliverySummary`
- `ExternalMessageRequest`
- `IncomingWebhookRequest`
- `Extension`

## OpenAPI Schema Definitions

| Schema name | Source | Used by endpoints | Notes / safety |
|---|---|---|---|
| `ApiSuccessResponse` | API response envelope foundation | Generic success responses | Base envelope only |
| `ApiErrorResponse` | API response envelope foundation | 4xx/5xx error responses | No debug trace/secrets |
| `ValidationErrorResponse` | Exception renderer / FormRequest failures | `422` responses | `errors` as field-to-array map |
| `PaginatedResponse` / `PaginationMeta` | Pagination foundation | List endpoints (users/chat/activity/etc.) | Standard meta keys |
| `User` | `UserResource`-style auth/meta payloads | auth, meta bootstrap | Safe identity fields only |
| `Role` | role resources / meta RBAC | roles endpoints, meta RBAC | no internal RBAC internals |
| `Permission` | permission resources / meta RBAC | permissions endpoints, meta RBAC | safe names/labels |
| `ChatConversation` | `ChatConversationResource` | conversation list/show/create | no secret/internal metadata |
| `ChatMessage` | `ChatMessageResource` | message list/send/edit | safe message contract |
| `ChatAttachment` | `ChatAttachmentResource` | attachment upload/list in message | no disk/path/checksum |
| `ChatParticipant` | participant resources | participant management routes | safe access/capability fields |
| `ChatDeviceRead` | admin read visibility payload | admin metadata/read visibility | no `device_key`, `user_agent`, `ip_address` |
| `ChatReadState` | read-state payloads | read state endpoints/resources | safe timestamps/ids |
| `ChatWebhookEndpoint` | `ChatWebhookEndpointResource` | webhook endpoint management | no `token_hash` / webhook secret |
| `ChatWebhookDeliverySummary` | `ChatWebhookDeliverySummaryResource` | webhook delivery list | no raw payload/response |
| `ExternalMessageRequest` | `SendExternalChatMessageRequest` contract | external message send | request schema only |
| `IncomingWebhookRequest` | `IncomingChatWebhookRequest` contract | incoming external webhook | request schema only |
| `MetaBootstrapResponse` | meta bootstrap contract | `/api/v1/meta/bootstrap` | current user + effective permissions |
| `MetaRbacResponse` | meta RBAC contract | `/api/v1/meta/rbac` | roles/permissions/role map only |

## Known gaps before generator
- Legacy non-versioned `/api/*` routes still coexist with `/api/v1/*`; generator scope should prioritize `/api/v1/*`.
- Some controllers build paginated payload arrays inline instead of always calling `BaseController::paginatedResponse`.
- Not every endpoint uses dedicated `FormRequest` (some still validate inline `Request`).
- No selected OpenAPI package/annotations yet (this document is preparation only).

## Next step: API documentation generator
- Choose generator strategy (attributes/annotations vs route-introspection).
- Start with `/api/v1/*` only.
- Reuse schema candidates and route inventory from this document as source-of-truth.

## Swagger UI Try it out
- Docs UI: `/docs/api`
- OpenAPI JSON: `/docs/api.json`
- In local/testing, docs are accessible. In non-local environments, access is protected by `ApiDocsAccessMiddleware` and `Gate::allows('viewApiDocs')`.
- `Try it out` is enabled in Scramble UI config.

### Authorization flows in Swagger UI
- `BearerAuth`:
  - Use for protected `/api/v1/*` routes that require `auth:sanctum`.
  - In Swagger UI click `Authorize`, select `BearerAuth`, paste token value (without `Bearer` prefix).
- `SanctumSession`:
  - Documented as cookie auth (`laravel_session`) for session-first flows.
  - Practical Try-it usage in local is usually easier with bearer token unless browser session cookie is already established.
- `ExternalChatToken`:
  - Use for external chat routes such as `/api/v1/chat/external/messages`.
  - Token must have required scopes (for example `chat.external.messages.send`).
- `WebhookSignature` + `WebhookTimestamp`:
  - Documented for incoming webhook endpoint headers.
  - Signature and timestamp generation follows configured HMAC/tolerance rules.

### Security notes
- Do not place real production tokens/secrets in shared docs screenshots or examples.
- Docs/spec must not expose runtime secrets (`token_hash`, webhook secret values, raw signatures).

## API Docs Access Control
- Local/testing environments: `/docs/api` and `/docs/api.json` are available for developer workflow.
- Non-local environments: docs are protected by `ApiDocsAccessMiddleware`.
- Required permission: `api.docs.view` (checked by `Gate::allows('viewApiDocs')`).
- Recommended roles: `admin` and `developer` (if `developer` role exists in deployment RBAC setup).
- Do not expose docs routes publicly in production.

## Permission-Aware API Documentation
- Centralized endpoint-group map: `config/api-docs.php`.
- Resolver service: `App\Services\ApiDocsPermissionService`.
- Group keys covered:
  - `auth`
  - `users_rbac`
  - `dashboard_stats`
  - `notifications`
  - `chat`
  - `webhooks`
  - `external_api`
- Visibility model:
  - `api.docs.view` grants docs route access.
  - `api.docs.view.full` grants full group visibility for future filtered-spec mode.
  - groups can be marked `public`, `permissions_any`, `permissions_all`.
- This step does not filter `/docs/api.json` yet; it defines a stable permission map contract for the next phase.
