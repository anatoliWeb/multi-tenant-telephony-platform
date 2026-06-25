# Baseline Validation Report

Validation date: 2026-06-24

## Summary

The renamed baseline is operational and the Docker stack is stable.

Verified results:

- Laravel boots and serves the API.
- MySQL, Redis, Reverb, queue worker, Horizon, and the new scheduler service are running.
- Migrations and seeders complete.
- Angular build and tests pass.
- Vue build and tests pass.
- The complete backend suite now passes, including the tenant-aware RBAC regression that originally failed on tenant permission isolation.
- The NG8107 Angular template warning is resolved.
- Manual browser login, logout, and authenticated-state validation were confirmed by the project owner.
- Angular realtime connection status was confirmed as connected by the project owner.
- Direct chat, group chat, live realtime delivery, unread/read state, typing, presence, and permission-aware access were confirmed by the project owner.

Still open:

- None for Milestone 1 browser validation.
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

Latest verified totals after the RBAC isolation fix:

- Passed: `502`
- Failed: `0`
- Skipped: `21`
- Incomplete: `0`
- Risky: `0`
- Assertions: `16110`
- Duration: `699.48s`

Fixes made during validation:

- Seeded the RBAC version key before bumping it so cache invalidation works even when the key is missing.
- Updated the admin meta runtime test to invalidate the RBAC cache version before asserting role permissions.
- Updated the typing indicator test to clear the throttle key directly instead of relying on cache TTL travel.
- Added the missing active tenant membership to the tenant-permission isolation regression test.
- Added a tenant cache-isolation regression test that proves permissions do not leak across tenant switches.
- Pinned auth contract fixtures to the platform permission scope so tenant-scoped permissions do not get picked up accidentally.

## Manual Browser Validation

Recorded from the project owner's manual verification:

- Angular login passed.
- Angular logout passed.
- Angular authenticated user state loaded and survived refresh.
- Vue administration login passed.
- Vue administration logout passed.
- Vue administration authenticated state loaded and survived refresh.
- Direct chat between two browser sessions passed.
- Group chat passed.
- Realtime message delivery between two browser sessions passed.
- Unread and read states passed.
- Typing indicators passed.
- Presence passed.
- Permission-aware frontend access passed.
- Protected backend endpoints returned `403`.
- Unauthorized conversation access was rejected.

Mojibake investigation and fix:

- Root cause: the Angular locale source files contained garbled translation literals for some Ukrainian and German labels, which caused a subset of visible UI strings to render incorrectly.
- Affected files: [frontend/src/app/i18n/translations/uk.ts](E:/_programming_/_portfolio_git_hub_/multi-tenant-telephony-platform/frontend/src/app/i18n/translations/uk.ts) and [frontend/src/app/i18n/translations/de.ts](E:/_programming_/_portfolio_git_hub_/multi-tenant-telephony-platform/frontend/src/app/i18n/translations/de.ts).
- Fix: rewrote the locale files in canonical UTF-8 so the translations render correctly in Angular.
- Affected UI areas: sidebar, dashboard cards, realtime panel, footer, and other translation-driven labels.

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

Milestone 1: COMPLETE

## Stage 7 Addendum

Follow-up validation on 2026-06-25 confirmed the tenant-aware chat baseline on the live development database.

Verified:

- the pending chat tenant backfill migration was applied on development;
- a follow-up constraint migration enforced `NOT NULL` tenant ownership across tenant-owned chat tables;
- before migration the live chat tables contained:
  - conversations `6`
  - messages `324`
  - conversation_participants `25`
  - message_attachments `0`
  - message_reads `895`
  - message_device_reads `1211`
  - message_deliveries `1350`
  - external_message_mappings `0`
  - chat_webhook_endpoints `0`
  - chat_webhook_deliveries `0`
  - chat_user_devices `15`
- after migration no chat rows were lost and all enforced chat `tenant_id` null counts were `0`;
- `php artisan chat:verify-tenant-integrity --json` reported zero chat tenant mismatch counts on the development database;
- Manual browser validation was performed and confirmed by the project owner.

Owner-confirmed browser checks:

- two independent sessions;
- Tenant A direct chat;
- realtime replies without refresh;
- unread and read behavior;
- typing behavior;
- switching Tenant A to Tenant B clears active conversation, conversation list, messages, unread state, typing state, and presence state;
- Tenant A conversation URL is rejected under Tenant B;
- switching back restores only Tenant A data;
- logout clears chat state.
