# Realtime

## Purpose

This document explains the current realtime foundation for admin UI, chat, notifications, presence, typing indicators, diagnostics, security, and troubleshooting.

The project uses Laravel broadcasting with Reverb/WebSockets. This is the current monolith realtime runtime; no standalone realtime service is extracted now.

## Current Realtime Stack

- Laravel broadcasting with Reverb driver
- Reverb server using Pusher-compatible protocol
- Laravel Echo / Pusher-compatible frontend client
- Redis/queues for async broadcast jobs where relevant
- Vue Admin realtime client and topbar diagnostics
- Private and presence channel authorization through `routes/channels.php`

Core config files:

- `backend/config/broadcasting.php`
- `backend/config/reverb.php`
- `backend/routes/channels.php`
- `backend/resources/js/shared/services/realtime/*`

## Environment Variables

Backend/runtime:

- `BROADCAST_CONNECTION` / `BROADCAST_DRIVER`
- `QUEUE_CONNECTION`
- `REVERB_SERVER_HOST`
- `REVERB_SERVER_PORT`
- `REVERB_SERVER_PATH`
- `REVERB_HOST`
- `REVERB_PORT`
- `REVERB_SCHEME`
- `REVERB_BROADCAST_HOST`
- `REVERB_APP_ID`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- `REVERB_SCALING_ENABLED`

Frontend/Vite:

- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`
- `VITE_REVERB_FORCE_TLS`

Notes:

- `REVERB_BROADCAST_HOST` lets backend containers broadcast to the Docker service while browser clients use the public `REVERB_HOST`.
- Production should align HTTP/HTTPS with WS/WSS. Mixed protocol settings are a common source of browser connection failures.
- Do not paste real Reverb app secrets or tokens into docs, logs, issues, or shell history.

## Realtime Channels

| Channel | Type | Purpose | Authorization |
| --- | --- | --- | --- |
| `private-chat.conversation.{id}` | private | Chat message/read/delivery/typing/attachment/participant events | Conversation must exist and `ChatAccessService::canViewMessages` must allow access |
| `presence-chat.{id}` | presence | Conversation presence and safe participant presence payload | Conversation must exist and `ChatPresenceService::canJoinPresence` must allow access |
| `chat.{id}` | presence legacy alias | Backward-compatible alias for older frontend builds | Same policy as `presence-chat.{id}` |
| `private-notifications.user.{userId}` | private | User-specific notification events | Authenticated user must match `{userId}` |
| `private-system.notifications` | private | System notification stream | Requires `notifications.view` |
| `private-activity.stream` | private | Activity stream events | Requires `activity.view` |
| `presence-online` | presence | Global online-user presence summary | Authenticated users only |
| `presence-dashboard` | presence | Dashboard presence summary | Authenticated users only |
| `presence-page.{page}` | presence | Page/group presence diagnostics | Authenticated users; page key must match allowed pattern |
| `presence-typing.{context}` | presence | Typing/presence context diagnostics | Authenticated users; context key must match allowed pattern |
| `test-broadcast` | public smoke | Dedicated smoke/test broadcast channel | Public test channel only; not for sensitive payloads |

## Chat Events

Chat broadcast events are sent to `private-chat.conversation.{id}` and use the `realtime` queue.

Chat channels are tenant-bound through the active request context and conversation ownership checks. The channel names remain backward-compatible for the existing frontend because conversation IDs are globally unique and authorization always resolves the conversation inside the current tenant before the payload is broadcast.

Current event names include:

- `chat.message.created`
- `chat.message.updated`
- `chat.message.deleted`
- `chat.message.read`
- `chat.message.device_read`
- `chat.message.delivery_updated`
- `chat.participant.access_changed`
- `chat.attachment.created`
- `chat.attachment.deleted`
- `chat.typing.started`
- `chat.typing.stopped`
- `chat.user.joined`
- `chat.user.left`

Typing and presence events use the same tenant-owned conversation root so a subscription from another tenant cannot authorize even if the UUID is known.

Decision:

- final channel format stays `private-chat.conversation.{id}` and `presence-chat.{id}`;
- no tenant-qualified rename was required;
- the real security boundary is tenant-scoped authorization plus globally unique conversation IDs;
- structured logs keep tenant context for diagnostics, so retained channel names do not hide tenant identity operationally.

Payloads are intentionally shaped by services/events and must stay safe for the subscribed audience.

## Presence

Presence channels return safe identity data only.

Allowed baseline fields:

- `id`
- `name`
- safe role/device hints only when explicitly provided by the presence service

Forbidden fields:

- email
- token / token_hash
- authorization / cookie / signature
- secret / webhook_secret
- device_key
- user_agent
- ip_address
- raw metadata or permission arrays

Hidden, blocked, removed, outsider, and unauthorized users are denied by channel authorization/domain services.

## Vue Admin Diagnostics

The Vue Admin topbar uses realtime counters as a compact diagnostics surface:

- `WS`: websocket connection state; active means the frontend considers the websocket connected/ready.
- `EV`: realtime events received by the frontend client.
- `ON`: unique online users seen across joined presence channels.
- `PG`: joined presence groups/pages/channels.

Manual check:

1. Login to Vue Admin.
2. Open dashboard or chat/admin pages.
3. Confirm `WS` becomes active.
4. Trigger a notification/chat/activity event and confirm `EV` increases.
5. Join a presence-backed page/chat and confirm `ON` or `PG` changes.
6. Check browser console diagnostics in local/dev when needed.

## Security Rules

- Do not put sensitive user/admin/chat data on public channels.
- Private and presence channels must authorize through `routes/channels.php` and domain services.
- Chat channel access must respect participant visibility/access state.
- Presence payloads must stay minimal and safe.
- Broadcast payloads must not include tokens, secrets, raw payloads, raw responses, storage paths, device keys, or authorization headers.
- `/broadcasting/auth` failures should return safe 401/403 responses without traces or secrets.
- production HTTP requests do not receive an implicit default tenant during chat channel authorization.

See `backend/docs/security.md` for the broader realtime channel authorization policy.

## Logging and Monitoring

Realtime logging is intentionally focused on security-significant events:

- denied channel authorization attempts
- broadcast failures when integrated
- safe structured context only

`RealtimeLogService` writes sanitized logs such as `realtime.channel.auth.denied`.

Logging policy:

- Denials/failures may be warning/error level.
- Successful high-volume realtime events should not generate noisy info logs.
- Log context is sanitized by structured logging helpers.

See `backend/docs/monitoring.md` for logging and observability details.

## Troubleshooting

### `WS` stays 0 / disconnected

- Check `VITE_REVERB_APP_KEY` is present.
- Check `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`, and `VITE_REVERB_FORCE_TLS`.
- Check backend `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, and `REVERB_BROADCAST_HOST`.
- Check browser console for websocket protocol errors.
- Check Reverb container logs.

### `EV` stays 0

- Confirm websocket is connected first.
- Confirm the expected event is actually dispatched.
- Confirm queue worker is running if the event is queued.
- Check `docker compose logs queue-worker --tail=100`.
- Check `docker compose logs reverb --tail=100`.

### `ON` stays 0

- Confirm presence channels are joined.
- Check `/broadcasting/auth` status in browser network tools.
- Verify authenticated session/token is valid.
- Check denied channel auth logs.

### `PG` stays 0

- Confirm the page/chat subscribes to a presence channel.
- Check `presence-page.{page}` or `presence-chat.{id}` authorization.
- Ensure page/context values match allowed pattern.

### `/broadcasting/auth` returns 401/403

- `401`: user is not authenticated or session/token is missing.
- `403`: user is authenticated but channel policy denies access.
- For chat channels, verify participant state and permissions.
- For system channels, verify required permissions such as `notifications.view` or `activity.view`.

### Protocol mismatch

- Local HTTP normally uses `ws`.
- HTTPS production normally needs `wss`.
- Mismatched `http/https/ws/wss` settings can block browser connections.

### Useful commands

```bash
docker compose logs reverb --tail=100
docker compose logs backend --tail=100
docker compose logs queue-worker --tail=100
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan cache:clear
```

## Testing

Realtime/security test filters:

```bash
docker compose exec backend php -d memory_limit=512M artisan test --filter=SecurityRealtimeChannelAuthorization --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=ChatRealtimeSafePayload --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=ChatPresenceSafePayload --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=RealtimeLogging --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=ChatRealtime --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=ChatPresence --stop-on-failure
```

Run these sequentially against the shared testing database unless separate parallel DBs are configured.

## Related Docs

- `backend/docs/architecture.md`
- `backend/docs/security.md`
- `backend/docs/monitoring.md`
- `backend/docs/deployment.md`
- `backend/docs/microservices.md`
- `backend/docs/commands.md`
