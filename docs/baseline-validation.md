# Baseline Validation Report

Validation date: 2026-06-23

## Summary

The renamed baseline is operational and the Docker stack is stable.

Verified results:

- Laravel boots and serves the API.
- MySQL, Redis, Reverb, queue worker, Horizon, and the new scheduler service are running.
- Migrations and seeders complete.
- Angular build and tests pass.
- Vue build and tests pass.
- The complete backend suite now passes.
- The NG8107 Angular template warning is resolved.

Still open:

- Real browser authentication was not completed in this environment.
- Real browser chat and realtime validation was not completed in this environment.
- The `pusher-js` Angular CommonJS warning remains as accepted technical debt.
- The Vue Dart Sass legacy JS API warning remains as accepted technical debt.

## Docker Service Matrix

| Service | Status | Notes |
| --- | --- | --- |
| Backend | Up, healthy | Laravel API runtime verified |
| Frontend | Up, healthy | Angular build and tests pass |
| Vue frontend | Up | Vue build and tests pass |
| MySQL | Up, healthy | Test and dev databases operational |
| Redis | Up, healthy | Cache, queue, and realtime support |
| Nginx | Up | HTTP entrypoint is reachable |
| Queue worker | Up | Redis queue processing active |
| Reverb | Up | Websocket server available on port 6001 |
| Horizon | Up | `php artisan horizon:status` reports running |
| Scheduler | Up | `php artisan schedule:work` executes the heartbeat task |

## Backend Test Results

Command:

```bash
docker compose exec -T backend php artisan test
```

Latest verified totals:

- Passed: `495`
- Failed: `0`
- Skipped: `21`
- Incomplete: `0`
- Risky: `0`
- Assertions: `16096`
- Duration: `797.94s`

Fixes made during validation:

- Seeded the RBAC version key before bumping it so cache invalidation works even when the key is missing.
- Updated the admin meta runtime test to invalidate the RBAC cache version before asserting role permissions.
- Updated the typing indicator test to clear the throttle key directly instead of relying on cache TTL travel.

## Browser Validation

Browser flows were not completed here because the in-app browser runtime failed before it could execute the UI walkthrough.

Observed blocker:

- the Node/browser runtime reported `codex/sandbox-state-meta: missing field 'sandboxPolicy'`.

Result:

- browser authentication: not verified
- browser chat: not verified
- browser realtime: not verified

## Horizon Validation

Verified:

- `docker compose ps` shows the dedicated `horizon` service running.
- `docker compose exec -T backend php artisan horizon:status` reports `INFO  Horizon is running.`
- Horizon logs show active job processing.
- Access control is covered by the existing Horizon access tests.

## Scheduler Validation

Verified:

- `docker-compose.yml` now includes a dedicated `scheduler` service.
- The service runs the Laravel-supported command `php artisan schedule:work`.
- Scheduler logs show the `scheduler-heartbeat` task executing repeatedly.
- Docker Compose confirms the scheduler container stays up.

## Frontend Warnings

- Angular NG8107 optional chaining warning:
  - root cause: the template used optional chaining on a value that is now known to be non-null.
  - fix: the template was updated to use direct property access.
  - status: resolved.
- Angular `pusher-js` CommonJS warning:
  - root cause: the current realtime package still resolves through a CommonJS build.
  - fix: not changed; no safe ESM migration was verified in this baseline task.
  - status: accepted technical debt.
- Vue Sass legacy JS API warning:
  - root cause: dependency/tooling deprecation warning from the Sass pipeline.
  - fix: not changed; no safe dependency/configuration update was verified.
  - status: accepted technical debt.

## Remaining Issues

- Browser authentication still needs real UI verification.
- Browser chat and realtime still need real UI verification.
- The two frontend warnings above remain accepted technical debt.

## Final Verdict

The Milestone 1 baseline is partially complete and ready for continued work, but it is not yet closed because the browser validation items remain unverified.
