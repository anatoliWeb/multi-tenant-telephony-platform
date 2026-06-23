# Queue Operations Runbook

This runbook describes minimal operational commands for Laravel queue workers and failed jobs.

## Scope

- Backend queue driver: `redis`
- Queue worker container: `multi_tenant_telephony_platform_queue_worker`
- Failed jobs storage: `failed_jobs` table

## Check Queue Worker Status

```bash
docker compose ps queue-worker
docker compose logs -f queue-worker
```

## Horizon Service (Monitoring Mode)

Start Horizon service:

```bash
docker compose up -d horizon
docker compose logs -f horizon
```

Check Horizon runtime status:

```bash
docker compose exec backend php artisan horizon:status
```

Dashboard URL:

```text
/horizon
```

Security:

- Horizon dashboard is protected by `web` + `auth` middleware
- Access requires RBAC permission: `system.monitoring`

Important processing mode note:

- Use either `queue-worker` or `horizon` as the active queue processor.
- Running both simultaneously can process the same queues in parallel and is not recommended for normal operations.

## Quick Queue Diagnostics Baseline

Run compact diagnostics command:

```bash
docker compose exec backend php artisan system:queue-status
```

It reports:

- queue connection driver
- failed jobs count
- Redis status (when queue driver is redis)
- queue worker logs hint

## Inspect Failed Jobs

List failed jobs:

```bash
docker compose exec backend php artisan queue:failed
```

## Retry Failed Jobs

Retry a single failed job by UUID:

```bash
docker compose exec backend php artisan queue:retry <failed-job-uuid>
```

Retry all failed jobs:

```bash
docker compose exec backend php artisan queue:retry all
```

## Forget / Delete Failed Jobs

Delete one failed job by UUID:

```bash
docker compose exec backend php artisan queue:forget <failed-job-uuid>
```

Delete all failed jobs:

```bash
docker compose exec backend php artisan queue:flush
```

## Queue Worker Runtime Settings (Current)

Worker command is managed by Supervisor:

```text
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Config source:

- `docker/supervisor/supervisord.conf`
- `backend/docker/queue/entrypoint.sh`
- `backend/config/horizon.php` (Horizon mode)

## Job-Level Retry Policy (Activity)

`LogActivityJob` defines explicit retry policy:

- `tries = 3`
- `timeout = 60`
- `backoff = [10, 30, 60]`

This policy is aligned with worker-level guardrails and keeps retry behavior explicit at job level.

## Notification Queue Foundation

`CreateNotificationJob` provides queued notification creation baseline:

- queue: `notifications`
- `tries = 3`
- `timeout = 60`
- `backoff = [10, 30, 60]`

Service entrypoint:

- `NotificationService::dispatchForUser(...)` for async delivery
- `NotificationService::createForUser(...)` remains synchronous for existing API flows

## Realtime Broadcast Queue Foundation

`BroadcastSystemNotificationJob` provides queued realtime broadcast baseline:

- queue: `realtime`
- `tries = 3`
- `timeout = 60`
- `backoff = [10, 30, 60]`

Service entrypoint:

- `SocketService::broadcastSystemNotification(...)` now dispatches queued realtime job

## Email Queue Foundation

`SendSystemEmailJob` provides queued system email baseline:

- queue: `emails`
- `tries = 3`
- `timeout = 60`
- `backoff = [10, 30, 60]`

Delivery implementation:

- Job sends `SystemEmailMailable` via `Mail::to(...)->send(...)`
- Foundation is transport-agnostic and works with current `MAIL_MAILER=log`

## Horizon Queue Coverage

Configured Horizon queues:

- `default`
- `activity`
- `notifications`
- `realtime`
- `emails`

## Notes

- `queue:flush` is destructive and should be used only when failed payloads are no longer needed.
- Retry commands should be executed after root-cause investigation to avoid repeated failures.
- Horizon is available as an optional monitoring mode and must remain protected by RBAC.
