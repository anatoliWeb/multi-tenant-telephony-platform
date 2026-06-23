# Realtime Operations Runbook

## Purpose
This runbook describes operational setup and smoke checks for Reverb/WebSocket realtime delivery across:
- Backend (Laravel + Reverb + queue job pipeline)
- Vue Admin client
- Angular Dashboard client

This is an operations guide only. It does not change API contracts or channel/event payloads.

## Realtime Flow Summary
1. Client triggers `POST /api/v1/realtime/notify`.
2. Backend dispatches `BroadcastSystemNotificationJob` to `realtime` queue.
3. Job dispatches `SystemNotificationEvent`.
4. Event is broadcast to:
   - public channel `system.notifications` (backward-compatible smoke path)
   - private channel `private-system.notifications` (authorized path)
   as `.system.notification`.
5. Activity writes broadcast to private channel `private-activity.stream` as `.activity.logged`.
6. Database notification domain bridge broadcasts to owner-only private channel:
   - channel: `private-notifications.user.{userId}`
   - event: `.notification.created`
   - safe payload: `id,type,title,message,is_read,read_at,created_at`
   - no raw `data/meta` broadcast
6. Presence channels provide online/session membership foundation:
   - `presence-online`
   - `presence-dashboard`
   - `presence-page.{page}`
   - `presence-typing.{context}`
5. Vue and Angular clients receive payload:
   - `type`
   - `title`
   - `message`
   - `created_at`

## Environment Configuration

### Backend / Reverb
Use `.env` (see `.env.example` for defaults):
- `REVERB_APP_ID`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- `REVERB_HOST`
- `REVERB_PORT`
- `REVERB_SCHEME`
- `REVERB_BROADCAST_HOST`

Notes:
- Browser clients should use a host reachable from browser context (usually `localhost` in local Docker).
- `REVERB_BROADCAST_HOST` can differ from browser host when backend talks to internal Docker service name.

### Vue Admin (Vite)
Use backend Vite env vars:
- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`
- `VITE_REVERB_FORCE_TLS`
- `VITE_REVERB_USE_PRIVATE_CHANNEL`

### Angular Dashboard
Angular reads realtime config from:
- `frontend/src/environments/environment.ts`
- `frontend/src/environments/environment.development.ts`

Key object:
- `environment.realtime`
- `environment.realtime.usePrivateChannel` toggles private vs public subscription mode.

Important:
- Angular does not read Vite env vars directly.
- Keep production values deployment-specific and avoid dev `localhost` defaults in production config.

## Docker Startup
Start required services:

```bash
docker compose up -d backend reverb queue-worker vue-frontend frontend
```

If Horizon profile is used instead of queue-worker:

```bash
docker compose --profile horizon up -d backend reverb horizon vue-frontend frontend
```

## Smoke Test Checklist

### 1) Authenticate
- Login via existing auth flow (session or token).
- Obtain bearer token if using token auth for API call.

### 2) Trigger realtime event
Example:

```bash
curl -X POST "http://localhost:8080/api/v1/realtime/notify" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"type":"info","title":"Smoke","message":"Realtime smoke message"}'
```

Expected API response includes:
- `success: true`
- `data.dispatched: true`

### 3) Verify Vue
- Realtime status should become connected (if websocket handshake succeeds).
- Realtime metrics counters update after event reception.
- Dev console should show realtime event/status logs (in dev mode).

### 4) Verify Angular
- Realtime service connects to Reverb and subscribes to `system.notifications`.
- In default mode clients subscribe to private `system.notifications` (Echo private channel).
- Dashboard/notifications components receive live event.
- Event appears in in-memory notifications feed / counters.

## Troubleshooting

### WebSocket does not connect
- Confirm `reverb` container is running.
- Check browser-reachable host/port (`localhost:6001` in local Docker).
- Validate key/host/port/scheme alignment between backend and client config.

### Wrong host/port
- Do not use internal Docker hostname in browser client config unless browser can resolve it.
- Use external host (commonly `localhost`) for frontend websocket endpoint.

### CORS/origin issue
- Check allowed origins and reverse proxy setup.
- Verify app is loaded from expected origin and websocket URL matches environment.

### App key mismatch
- `VITE_REVERB_APP_KEY` / Angular `environment.realtime.appKey` must match backend `REVERB_APP_KEY`.

### Queue worker not processing realtime jobs
- Check `queue-worker`/`horizon` is running.
- Ensure queue connection is healthy.
- Verify `realtime` queue is processed by worker configuration.
- If notifications are stored but no live update arrives, check `realtime.enabled` user preference.

### Reverb service not running
- Start `reverb` service.
- Inspect logs for bind/config errors.

### Browser cannot resolve internal Docker host
- Use browser-reachable host in frontend config (`localhost` for local dev).
- Keep internal host usage for backend broadcast connection where needed.

## Security Notes
- `system.notifications` is currently a public foundation channel.
- Private channel authorization rule is enabled for `system.notifications`.
- Required permission for private subscription: `notifications.view`.
- Owner-only domain notification channel is enabled:
  - `notifications.user.{userId}`
  - auth rule: current authenticated user id must equal `{userId}`
- Do not broadcast sensitive data on this channel.
- Current payload contract should stay limited to:
  - `type`
  - `title`
  - `message`
  - `created_at`
- Activity stream payload must remain safe/minimal:
  - `id`
  - `action`
  - `description`
  - `user.id`, `user.name` (optional)
  - `created_at`
  - optional safe `meta.source` / `meta.module`
- Never broadcast sensitive activity metadata (tokens, passwords, request payloads).
- Presence payload must remain safe/minimal:
  - `id`
  - `name`
- Do not include `email`, `roles`, `permissions`, tokens, or full user model dumps in presence data.
- Presence channel wildcard segments (`page`, `context`) must be sanitized.
- Public channel remains enabled temporarily for backward-compatible smoke checks.

### Notification Domain Bridge vs `system.notifications`
- `system.notifications` remains for generic smoke/system flow (public+private compatibility path).
- `notifications.user.{userId}` is dedicated domain bridge for persisted database notifications.
- If `system.enabled=false`, notification is not created and no domain broadcast is sent.
- If `system.enabled=true` but `realtime.enabled=false`, notification is stored in DB but domain broadcast is skipped.

## Presence Channels

### Authorization
- `presence-online`: authenticated users.
- `presence-dashboard`: authenticated users.
- `presence-page.{page}`: authenticated users + sanitized `{page}` segment.
- `presence-typing.{context}`: authenticated users + sanitized `{context}` segment.

Current sanitization policy for wildcard values:
- allowed characters: `a-z`, `0-9`, `.`, `_`, `:`, `-`
- max length: 64

### Presence Smoke
1. Open dashboard in two browser tabs/windows with authenticated users.
2. Join `presence-online` and `presence-dashboard` from Vue/Angular clients.
3. Confirm counters/users increase on second join and decrease on tab close.
4. Validate that no sensitive user fields are exposed in presence payload.

## Useful Commands

```bash
docker compose logs -f reverb
docker compose logs -f queue-worker
docker compose exec backend php artisan system:queue-status
docker compose exec backend php artisan horizon:status
```

Activity stream authorization check (authenticated user with `activity.view`):

```bash
curl -X POST "http://localhost:8080/broadcasting/auth" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"socket_id":"123.456","channel_name":"private-activity.stream"}'
```

If Horizon profile is not enabled, `horizon:status` can report inactive; this is expected.

## Validation Scope
- This runbook validates operational wiring and smoke behavior.
- It does not replace feature tests for queue/events/auth flows.
