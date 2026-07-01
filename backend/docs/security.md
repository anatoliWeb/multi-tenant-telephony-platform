# Security

## Rate Limiting

The API uses named rate limiters to protect critical endpoints without changing API contracts.

### Endpoint groups and limiter names

- Auth login/token endpoints: `throttle:auth-login`
- API docs routes (`/docs/api*`): `throttle:api-docs`
- Chat message send: `throttle:chat-message-send`
- Chat typing start/stop: `throttle:chat-typing`
- Chat attachment upload: `throttle:chat-attachments`
- Chat external API endpoints: `throttle:chat-external-api`
- Chat webhook management routes: `throttle:chat-webhook-management`

### Environment keys

- `SECURITY_RATE_LIMITS_ENABLED`
- `AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS`
- `AUTH_LOGIN_RATE_LIMIT_DECAY_SECONDS`
- `API_DOCS_RATE_LIMIT_MAX_ATTEMPTS`
- `API_DOCS_RATE_LIMIT_DECAY_SECONDS`
- `CHAT_TYPING_RATE_LIMIT_MAX_ATTEMPTS`
- `CHAT_TYPING_RATE_LIMIT_DECAY_SECONDS`
- `CHAT_ATTACHMENT_RATE_LIMIT_MAX_ATTEMPTS`
- `CHAT_ATTACHMENT_RATE_LIMIT_DECAY_SECONDS`

Existing chat limiter keys remain in `chat.php`:

- `CHAT_MESSAGE_SEND_RATE_LIMIT_*`
- `CHAT_EXTERNAL_API_RATE_LIMIT_*`
- `CHAT_WEBHOOK_MANAGEMENT_RATE_LIMIT_*`

### Key strategy

- Auth login: `email + ip`, fallback to `ip`
- API docs: `user_id`, fallback to `ip`
- Chat typing: `user_id + conversation_id + ip`
- Chat attachments: `user_id + message_id + ip`
- Existing chat limiters keep their current key strategy.

### 429 behavior

Laravel returns `429 Too Many Requests` with a safe body. The application does not include password/token/secret values in limiter keys or response payloads.

### Safety rules

- Never log raw passwords, bearer tokens, webhook secrets, or authorization headers.
- Do not apply aggressive throttles to normal read/list endpoints.
- Keep high-frequency endpoints (typing) softly throttled to avoid UX regressions.

## Secure Headers

Security headers are applied by Laravel middleware (`SecurityHeadersMiddleware`) for web, API, and docs responses.

### Enabled headers

- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `X-Frame-Options: SAMEORIGIN`
- `X-Permitted-Cross-Domain-Policies: none`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

### CSP policy

- Config key: `security.headers.content_security_policy.*`
- Default mode is enforced header (`Content-Security-Policy`).
- Optional report-only mode via `SECURITY_CSP_REPORT_ONLY=true`.
- Default policy is conservative and Swagger/dev compatible (includes inline/eval allowances for compatibility).

### HSTS policy

- Config key: `security.headers.hsts.*`
- Disabled by default (`SECURITY_HSTS_ENABLED=false`) for local/dev.
- Applied only when enabled and request is HTTPS.

### Local/dev behavior

- Headers can be disabled with `SECURITY_HEADERS_ENABLED=false` for targeted debugging.
- Docs access policy remains controlled by docs access middleware/gates; secure headers do not bypass permissions.
- Vue Admin local development keeps `script-src` and `connect-src` open to browser-reachable localhost Vite origins so `@vite/client` and WebSocket HMR can load on `/admin/login`.

### Production recommendations

- Enable HSTS only behind HTTPS.
- Tighten CSP incrementally after validating Swagger UI and admin assets.
- Consider switching CSP to report-only first when introducing stricter directives.

### Verify with curl

- `curl -I http://localhost:8080/api/v1/health`
- `curl -I http://localhost:8080/docs/api/portal`

## Validation Hardening

Validation follows a FormRequest-first policy for critical API endpoints.

- Auth login/session login use dedicated FormRequest classes.
- Chat payloads validate enums/types and body length bounds.
- Participant and conversation create flows validate referenced user IDs.
- External API payloads validate provider/external ID/idempotency key format and max lengths.
- Webhook endpoint requests validate event allowlists, URL shape, and scoped arrays.
- Attachment upload validation enforces file type and max size from config.

Safe validation error behavior:

- API validation failures return standardized `422` JSON envelope.
- Validation responses do not include secrets/tokens/signatures/storage paths.
- Invalid webhook signature remains `403` with safe generic message.

Known gaps:

- Full SSRF hardening for webhook target URLs is tracked separately and is not part of this validation-only step.

## Token Security

### Internal auth tokens

- API bearer tokens are issued via Laravel Sanctum and stored hashed in `personal_access_tokens.token`.
- Plain token values are returned only at issue/create time and are not persisted as plaintext.
- Token logout/revoke removes the current token and invalidates future access.
- Session auth (`/api/v1/auth/session/*`) remains the primary Vue Admin flow; bearer token flow is supported for API-first clients.

### User API tokens

- Token create/list/revoke endpoints are permission-protected (`tokens.create|view|delete`).
- Token list/resource payloads expose only safe metadata (id, name, scopes, owner, timestamps).
- `token`/`token_hash` are never returned by list endpoints.

### External chat API tokens

- External tokens are generated with a prefix and stored as hash only (`metadata.token_hash`).
- Scope enforcement is required through `external.chat.scope:*` middleware.
- Scope mismatch returns `403`; invalid token returns `401`.
- Token usage updates `last_used_at`/`token_last_used_at` metadata without exposing token secrets.
- Plain external token is revealed one-time on endpoint creation.

### Expiration and rotation

- Internal bearer token expiration is controlled by `SANCTUM_TOKEN_EXPIRATION`.
- External webhook token rotation is supported by webhook endpoint secret/token lifecycle flows.
- Existing contracts are preserved; no forced expiration migration is applied in this step.

### Frontend storage and safety notes

- Vue Admin keeps optional bearer fallback token in `localStorage` (`admin_access_token`).
- Stale bearer tokens are removed on `/v1/auth/me` failure before session fallback.
- Logout clears bearer token and calls session logout.
- Authorization headers/tokens must never be logged in frontend/backend logs.

## Realtime Channel Authorization

### Channel types and access rules

- `private-system.notifications`: requires `notifications.view`.
- `private-activity.stream`: requires `activity.view`.
- `private-notifications.user.{userId}`: owner-only (`auth user id === {userId}`).
- `private-chat.conversation.{conversationId}`: allowed for active, visible participants or privileged chat-admin paths allowed by chat access policy.
- `presence-chat.{conversationId}` and legacy alias `chat.{conversationId}`: same conversation access policy as presence join checks.
- `presence-online`, `presence-dashboard`, `presence-page.{page}`, `presence-typing.{context}`: guest denied; authenticated users only.

### Presence payload safety

Presence auth payloads are intentionally minimal and should include safe identity fields only (for example `id`, `name`, and safe role/device hints where applicable).
They must not expose sensitive fields such as:

- `email`
- `token` / `token_hash`
- `secret` / `authorization`
- `device_key`
- `user_agent`
- `ip_address`
- internal `permissions` arrays / raw metadata

### Event payload safety

Realtime event payloads for chat/messages/attachments/read/delivery/typing/participant access must remain safe and exclude secrets, storage internals, and raw debug payloads.

Architecture cross-reference:

- See `backend/docs/architecture.md` -> `## Event-Driven Module Communication` for global event naming and payload safety rules across modules.

### Known hardening note

`test-broadcast` is a dedicated smoke/test channel and is not used for user/admin/chat sensitive payloads.

## Docker Security Review

### Dev vs production assumptions

- Current Docker setup is development-first.
- Exposed ports (`APP_PORT`, `FRONT_PORT`, `3307`, `6379`, `6001`, `5173`) are for local workflow and should be restricted in production deployments.
- Do not treat local `docker-compose.yml` as production hardening baseline.

### Secrets and environment handling

- Secrets are injected via environment files at runtime (`env_file: .env`) and are not hardcoded in Dockerfiles.
- `.env` files are excluded from build context via `.dockerignore`.
- Never commit real tokens, DB credentials, or app keys into compose/Dockerfiles/scripts.

### Build context and ignored files

- `.dockerignore` excludes:
  - `.env` and local env variants
  - `node_modules`, `vendor`, logs, local cache artifacts
  - local docker data volumes (`docker/data/mysql`, `docker/data/redis`)

### Nginx hardening baseline

- Hidden files are denied (`location ~ /\.(?!well-known).*`), preventing direct access to `.env`, `.git`, etc.
- Directory listing is disabled (`autoindex off`).
- Static cache policy remains enabled for hashed assets.

### Runtime user and permissions note

- Some containers still run with default/root runtime users for local volume compatibility.
- For production, prefer explicit non-root users and least-privilege writable paths (`storage`, `bootstrap/cache`) after validating permissions.

### Healthchecks and observability

- Healthchecks exist for core local dependencies/services (`backend`, `frontend`, `mysql`, `redis`).
- Healthchecks should not log or expose secrets.

### Production hardening checklist

- Restrict published ports to required ingress only.
- Use non-root runtime users where feasible.
- Add network segmentation/firewall rules.
- Use managed secret stores instead of static env files.
- Keep image dependencies minimal and pinned.
