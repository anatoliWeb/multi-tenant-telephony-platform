# Performance

## Redis Caching

This project uses Laravel cache store abstraction with Redis as the recommended backend.

### What Is Cached
- Meta bootstrap payload (`/api/v1/meta/bootstrap`) with user-scoped key.
- RBAC payload sections (`roles`, `permissions`, `role_permissions`) with RBAC versioned keys.
- Effective user permissions with user + global RBAC versioned keys.
- Dashboard stats summary (`/api/v1/stats`) with short TTL.
- Filtered OpenAPI spec (`/docs/api.filtered.json`) with user + RBAC versioned key.

### What Is Not Cached
- Chat message lists and mutable conversation streams.
- Realtime/presence states.
- Tokens, secrets, signatures, authorization headers.
- Raw webhook payload/response bodies.

### Key Strategy
- `meta:bootstrap:user:{userId}:v{rbacVersion}:{userVersion}`
- `meta:rbac:roles:v{rbacVersion}`
- `meta:rbac:permissions:v{rbacVersion}`
- `meta:rbac:role_permissions:v{rbacVersion}`
- `rbac:user:{userId}:effective_permissions:v{globalVersion}:{userVersion}`
- `stats:dashboard:summary:v1`
- `docs:openapi:filtered:user:{userId}:full:{0|1}:rbac:{rbacVersion}:userv:{userVersion}`

### TTLs
Configured in `config/performance.php`:
- `PERFORMANCE_CACHE_DEFAULT_TTL`
- `PERFORMANCE_CACHE_META_TTL`
- `PERFORMANCE_CACHE_RBAC_TTL`
- `PERFORMANCE_CACHE_STATS_TTL`
- `PERFORMANCE_CACHE_API_DOCS_TTL`

### Invalidation
- RBAC changes: bump global RBAC cache version (no `Cache::flush()`).
- User role/permission changes: bump user bootstrap version + user permission version.
- Filtered docs cache naturally rotates with RBAC/user version changes.

### Safety Rules
- Never cache global copies of user-specific permission responses.
- Never cache sensitive fields: token, secret, authorization, webhook secrets, device keys, raw payloads.
- Keep mutable chat runtime endpoints uncached unless explicit bottleneck and strict invalidation plan exist.

### Local Commands
- `php artisan cache:clear`
- `php artisan optimize:clear`
- `docker compose exec redis redis-cli -a "$REDIS_PASSWORD" ping`

## Query Optimization

### Optimized Endpoints
- `GET /api/v1/chat/conversations`
- `GET /api/v1/chat/conversations/{conversation}/messages`
- `GET /api/v1/chat/conversations/{conversation}/webhook-deliveries`

### Changes Applied
- Replaced per-conversation unread `COUNT(*)` loop (N+1 pattern) with one batched unread-count query in `ChatConversationQueryService::unreadCountsForConversations`.
- Added composite index `messages(conversation_id, id)` for conversation message listing (`WHERE conversation_id ... ORDER BY id DESC` and pagination by `before_id`).
- Added composite index `chat_webhook_deliveries(conversation_id, id)` for conversation delivery history listing (`WHERE conversation_id ... ORDER BY id DESC`).

### Query Patterns
- Conversations list: participant-bound visibility + unread counters now computed in one grouped query.
- Messages list: conversation-scoped pagination on message id.
- Webhook deliveries list: conversation-scoped descending id timeline.

### Intentionally Not Optimized
- High-churn realtime/presence endpoints.
- Mutable chat message streams via response caching.
- Broad index additions not tied to observed query patterns.

### Verification
- Run `php -d memory_limit=512M artisan test --filter=QueryOptimization --stop-on-failure`.
- Check API contract remains unchanged via existing Chat/API test suites.

## Asset Optimization

### Vue Admin (Laravel + Vite)
- Production build uses minification and CSS minification.
- Production sourcemaps are disabled by default and controlled via `VITE_BUILD_SOURCEMAP`.
- Optional console stripping is controlled via `VITE_DROP_CONSOLE` (default `false` for safe behavior).
- Manual chunk strategy separates heavy vendor domains:
  - `vendor-vue` (vue/pinia/router)
  - `vendor-i18n` (vue-i18n)
  - `vendor-charts` (chart.js/vue-chartjs)
  - `vendor-realtime` (echo/pusher)
- Output stays hashed via Vite manifest build output in `public/build`.

### Angular Dashboard
- Production config explicitly enforces:
  - `optimization: true`
  - `outputHashing: all`
  - `sourceMap: false`
  - `namedChunks: false`
  - `vendorChunk: false`
  - `buildOptimizer: true`
- Budgets remain enabled for initial bundle and component styles.

### Static Cache Headers (Nginx)
- Immutable long-cache headers for hashed build assets under `/build/assets/*`.
- Long cache for generic static files (`.js`, `.css`, images, fonts).
- HTML responses are marked `no-cache/no-store` to avoid stale app shell.
- API routes are not treated as static assets.

### Verify
- `docker compose exec backend npm run build`
- `docker compose exec frontend npm run build`
- `docker compose exec nginx nginx -t`

## Queue Performance Optimization

### Queue Connection
- Default queue connection is Redis (`QUEUE_CONNECTION=redis`).
- Redis queue settings are configurable via:
  - `REDIS_QUEUE_CONNECTION`
  - `REDIS_QUEUE`
  - `REDIS_QUEUE_RETRY_AFTER`
  - `REDIS_QUEUE_BLOCK_FOR`

### Queue Priorities / Names
- Worker priority order:
  - `webhooks`
  - `realtime`
  - `notifications`
  - `activity`
  - `emails`
  - `default`
  - `low`
- Webhook delivery jobs run on dedicated `webhooks` queue.
- Realtime broadcast jobs stay on `realtime` queue for UX responsiveness.

### Worker Strategy (Supervisor)
- Worker command uses Redis queue with sane defaults:
  - `--sleep=1`
  - `--tries=3`
  - `--timeout=90`
  - `--backoff=10`
  - `--max-time=3600`
  - `--max-jobs=1000`
- Queue worker container waits for Redis + MySQL readiness before supervisor start.
- Container restart policy remains enabled (`autorestart=true`).

### Job Retry / Timeout / Backoff
- Critical async jobs define explicit retries/timeouts/backoff.
- `DeliverChatWebhookJob`:
  - queue: `webhooks`
  - tries: `3`
  - timeout: `15`
  - backoff: `[5, 15, 30]`

### Failed Jobs / Monitoring
- `failed_jobs` table is present and indexed.
- Failed jobs driver: `database-uuids`.
- Operational commands:
  - `php artisan queue:restart`
  - `php artisan queue:failed`
  - `php artisan queue:retry all`
  - `php artisan queue:flush`
  - `php artisan system:queue-status`

### Safety Rules
- Do not switch production workers to `sync`.
- Do not disable failed jobs tracking.
- Do not log secrets/tokens/raw authorization in queue payload logs.
- Keep idempotent behavior for external/webhook flows when tuning retries.
