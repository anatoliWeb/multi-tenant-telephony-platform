# Monitoring

## Queue Logging

Queue logging is enabled by default and is focused on critical queue lifecycle visibility with safe structured context.

### Configuration

- `LOG_QUEUE_EVENTS=true|false`
- Runtime key: `config('logging.queue.enabled')`

### Logged lifecycle events (critical jobs)

For `DeliverChatWebhookJob`:

- `queue.webhooks.delivery.started`
- `queue.webhooks.delivery.completed`
- `queue.webhooks.delivery.retry_scheduled`
- `queue.webhooks.delivery.failed` / exception variants
- `queue.webhooks.delivery.cancelled`

### Safe context fields

- `job_class`
- `queue`
- `job_delivery_id`
- `delivery_id`
- `delivery_uuid`
- `webhook_endpoint_id`
- `event`
- `attempt`
- `max_tries`
- `status`
- `response_status`
- `duration_ms`
- `error_class` / `error_summary` (on failure)

### Never log

- `token`, `token_hash`
- `secret`, `webhook_secret`
- `signature`, `authorization`
- `raw_payload`, `raw_response`, full `payload`, `response_body`
- `device_key`, `user_agent`, `ip_address`

### Operational checks

- `php artisan queue:failed`
- `php artisan queue:retry all`
- `php artisan queue:restart`

Queue logging must remain signal-oriented and avoid noisy per-record payload dumps.

## Realtime Logs

Realtime logging is focused on security-significant lifecycle events, not on high-volume message/event payload dumps.

Architecture cross-reference:

- See `backend/docs/architecture.md` -> `## Event-Driven Module Communication` for event taxonomy and allowed cross-module event paths.

### Configuration

- `LOG_REALTIME_EVENTS=true|false`
- `LOG_REALTIME_CHANNEL_AUTH_FAILURES=true|false`
- `LOG_REALTIME_BROADCAST_FAILURES=true|false`
- `LOG_REALTIME_PRESENCE_SUMMARY=false` (reserved for low-noise summaries)

Runtime keys:

- `config('logging.realtime.enabled')`
- `config('logging.realtime.channel_auth_failures')`
- `config('logging.realtime.broadcast_failures')`

### What is logged

- denied private/presence channel authorization attempts
- broadcast failure events when explicitly integrated

### What is not logged

- full broadcast payloads
- message bodies / raw payloads
- authorization headers / cookies / signatures
- tokens / secrets / webhook secrets
- device key, user-agent, IP address

### Safe context examples

- `channel_name`
- `channel_type`
- `user_id`
- `conversation_id` (if applicable)
- `reason`
- `status`

Use `warning`/`error` levels for denials/failures and avoid noisy `info` logs for successful high-volume channel auth/events.

## Structured Logs

Structured logging is standardized via `StructuredLogContextService` to keep log context consistent across request/error/queue/realtime/monitoring flows.

### Standard context fields

When applicable, logs should include:

- `event`
- `category`
- `module`
- `status`
- `request_id`
- `user_id` / `actor_id`
- `route`, `method`, `path`
- `duration_ms`
- `job_class`, `queue`, `attempt`
- `error_class`, `error_summary`

Not every event contains every key, but the shape should remain predictable.

### Sensitive field stripping

The shared sanitizer recursively removes forbidden keys, including:

- `token`, `access_token`, `refresh_token`, `token_hash`
- `authorization`, `cookie`, `password`
- `secret`, `signature`, `webhook_secret`
- `raw_payload`, `raw_response`, `payload`, `response_body`
- `device_key`, `user_agent`, `ip_address`
- storage internals (`disk`, `checksum`, `storage_path`)

### Request correlation policy

- `LogRequestMiddleware` attaches/generates `request_id` and sets `X-Request-Id` response header.
- Queue and realtime logs include structured category/module/action context for easier cross-event tracing.

### Examples

- request: `event=http.request.completed`, `category=request`, `module=api`
- queue: `queue.webhooks.delivery.*`, `category=queue`, `module=chat.webhooks`
- realtime denied: `realtime.channel.auth.denied`, `category=realtime`, `module=broadcast`
- monitoring readiness: safe degraded summaries without secrets or traces

## Monitoring Preparation

Lightweight monitoring foundation is available without introducing a heavy Prometheus/Grafana stack.

### Endpoints

- `GET /health` (public liveness)
  - returns:
    ```json
    {
      "status": "ok"
    }
    ```
  - does not run dependency checks and does not expose internals.

- `GET /api/v1/system/health` (protected readiness)
  - middleware: `auth:sanctum` + `permission:system.monitoring`
  - returns safe readiness summary in the standardized API envelope:
    - overall status: `ok` or `degraded`
    - checks: `database`, `redis`, `cache`, `queue`

### Safety policy

- no credentials, tokens, secrets, env dumps, stack traces, or absolute paths in responses.
- dependency failures are sanitized to status-only check results.
- detailed internal diagnostics stay outside public HTTP responses.

### Config

`config/monitoring.php` / `.env` keys:

- `MONITORING_HEALTH_ENABLED`
- `MONITORING_HEALTH_PROTECTED_ENABLED`
- `MONITORING_HEALTH_TIMEOUT_MS`
- `MONITORING_HEALTH_EXPOSE_DETAILS`
- `MONITORING_HEALTH_CHECK_DATABASE`
- `MONITORING_HEALTH_CHECK_REDIS`
- `MONITORING_HEALTH_CHECK_CACHE`
- `MONITORING_HEALTH_CHECK_QUEUE`

### Docker usage

- backend container healthcheck remains lightweight (`php -v`) for dev stability.
- application-level readiness should be verified through `/api/v1/system/health`.

### Future path

- add metrics exporter integration later (Prometheus/OpenTelemetry) without changing current endpoint contracts.

## Container Log Strategy

### Policy summary

- Keep container logs on `stdout/stderr` for Docker-native observability.
- Keep structured/sanitized Laravel context fields enabled.
- Never log secrets/tokens/passwords/raw payload bodies.
- Use bounded Docker log retention to avoid unbounded disk growth.

### Laravel / backend

- Current local default in `.env.example` remains `LOG_CHANNEL=stack` + `LOG_STACK=single` for developer convenience.
- Container-ready recommendation:
  - `LOG_CHANNEL=stack`
  - `LOG_STACK=stderr`
  - or `LOG_CHANNEL=stderr`
- Keep `LOG_LEVEL=debug` for local and prefer `info` (or stricter) for production.

### Queue / supervisor

- Queue worker runtime logs are emitted through Laravel logging channels.
- `supervisord` writes worker `stdout/stderr` to container streams:
  - `stdout_logfile=/dev/stdout`
  - `stderr_logfile=/dev/stderr`

### Nginx

- `access_log /dev/stdout`
- `error_log /dev/stderr warn`
- No request body logging is configured.

### Docker compose logging options

`docker-compose.yml` uses JSON-file log rotation defaults:

- `driver: json-file`
- `max-size: 10m`
- `max-file: 3`

### Useful commands

- `docker compose logs backend --tail=100`
- `docker compose logs queue-worker --tail=100`
- `docker compose logs nginx --tail=100`
- `docker compose logs --since=10m`
