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

## Current Access Validation Focus

Current tenant-access validation should confirm:

- `platform-admin@test.local` can open Vue Admin with platform permissions;
- Platform Admin sees all active tenants but receives tenant permissions only after explicit tenant selection;
- Angular tenant navigation remains hidden until a tenant is selected;
- Angular public settings preload returns `200` before login and does not expose private settings;
- tenant users receive only role-based tenant permissions;
- tenant APIs stay scoped to the selected tenant;
- Vue Admin exposes platform support navigation for tenants, contacts,
  extensions, phone numbers, and call logs.

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
- Some local backend verification runs can still enter a broken `saas_testing`
  migration state if resets or suites overlap; use sequential reruns only.

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

Backend verification follow-up on 2026-06-25:

- root cause of the earlier partial state: timed-out `php artisan test` sessions left abandoned `saas_testing` migration and DDL activity behind;
- resolved test context before cleanup: environment `testing`, database `saas_testing`;
- testing safety guard was re-verified through `Tests\Unit\TestingDatabaseGuardTest`;
- safe cleanup performed: inspected backend container processes and MySQL process lists, waited for one still-active targeted rerun to finish its DDL work, and confirmed the lingering `php artisan test` worker and `saas_testing` session had exited before starting the final clean reruns;
- targeted Stage 7 chat rerun: `19` passed, `0` failed, `0` skipped, `398` assertions, `516.23s`;
- tenant-isolation rerun: `7` passed, `0` failed, `0` skipped, `27` assertions, `426.78s`;
- external-chat regression rerun: `3` passed, `0` failed, `0` skipped, `27` assertions, `422.61s`;
- full backend suite rerun: `516` passed, `0` failed, `21` skipped, `16402` assertions, `850.41s`;
- development chat counts remained unchanged after testing:
  - conversations `6`
  - messages `324`
  - conversation_participants `25`
  - message_reads `895`
  - message_device_reads `1211`
  - message_deliveries `1350`
  - chat_user_devices `15`
- integrity recheck after all reruns still reported zero null tenant ownership rows, zero tenant mismatch rows, zero duplicate tenant-scoped device rows, and no data loss;
- Stage 7 is now complete;
- Milestone 2 remains partial because notifications tenancy, activity-log tenancy, queue and listener propagation, scheduler propagation, and broader tenant-isolation work remain unfinished;
- the next TODO item is now `Step 5: Begin Telephony Foundation` -> `[ ] Shared telephony contracts`.

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

## Stage 10 Addendum

Follow-up validation on 2026-06-25 confirmed the tenant-aware Contacts baseline on both the development and testing databases.

Verified:

- the pending development migration `2026_06_25_100000_create_contacts_tables` was applied without data loss;
- development counts before and after migration remained stable for existing datasets:
  - users `22` -> `22`
  - tenants `3` -> `3`
  - conversations `6` -> `6`
  - messages `324` -> `324`
- new development contact tables were created with zero unexpected seed rows:
  - contacts `0`
  - contact_phones `0`
  - contact_emails `0`
  - contact_tags `0`
- contacts schema verification confirmed tenant and lookup indexes on:
  - `contacts`
  - `contact_phones`
  - `contact_tags`
- targeted contacts verification:
  - `11` passed
  - `0` failed
  - `0` skipped
  - `81` assertions
  - `463.02s`
- full backend verification after the contacts slice:
  - `547` passed
  - `0` failed
  - `21` skipped
  - `17048` assertions
  - `876.71s`
- Angular tenant contacts validation:
  - build passed inside the running frontend container;
  - test suite passed with `19` files and `128` tests;
  - tenant-aware contacts lazy chunk was emitted successfully.

Contacts-specific notes:

- Contacts are implemented.
- Real calls are not implemented.
- FreeSWITCH is not installed.
- SIP.js is not integrated.

## Stage 11 Addendum

Follow-up validation on 2026-06-25 confirmed the tenant-aware Extensions baseline on the development and testing environments.

Verified:

- the additive development migration for `extensions` and `extension_credentials` applied without data loss;
- existing development counts for users, tenants, contacts, conversations, and messages were preserved;
- extension tables were created with tenant foreign keys, tenant-scoped uniqueness, and assignment indexes;
- fake-provider-backed endpoint provisioning works through the shared telephony contracts;
- plaintext extension secrets are displayed only once and are not exposed through ordinary list or detail APIs;
- Angular build passed with the lazy-loaded extensions feature module;
- Angular tests passed after adding an explicit `pusher-js` constructor mock in the existing realtime spec.

Extensions-specific notes:

- Extensions are implemented.
- Provisioning uses the fake provider only.
- FreeSWITCH is not installed.
- SIP.js is not integrated.
- Real SIP registration is not implemented.

## Stage 11 DID Addendum

Follow-up validation on 2026-06-26 confirmed the tenant-aware DID baseline on the development and testing environments.

Verified:

- the additive development migration for `phone_numbers` preserved existing development data counts;
- DID uniqueness is tenant-scoped and primary DID consistency is enforced transactionally;
- user assignment stays inside the active tenant and does not bind DIDs directly to extensions;
- Angular now exposes a tenant DID inventory screen with assignment, primary selection, and tenant-switch reset behavior.

DID-specific notes:

- DIDs are implemented.
- FreeSWITCH is not installed.
- SIP.js is not integrated.
- Real routing and calls are not implemented.

## Stage 12 Addendum

Follow-up validation on 2026-06-26 confirmed the tenant-aware Call Logs and Statistics baseline on the development and testing environments.

Verified:

- the additive development migration for `call_logs` and `call_events` applied without changing existing development data counts;
- existing development counts remained stable before and after migration:
  - users `22` -> `22`
  - tenants `3` -> `3`
  - contacts `0` -> `0`
  - extensions `0` -> `0`
  - phone_numbers `0` -> `0`
  - conversations `6` -> `6`
  - messages `324` -> `324`
- targeted call-log verification:
  - `4` passed
  - `0` failed
  - `0` skipped
  - `23` assertions
  - `10.82s`
- targeted telephony, seeder, and call-log regression rerun:
  - `12` passed
  - `0` failed
  - `0` skipped
  - `82` assertions
  - `530.91s`
- Angular tenant call-log validation:
  - build passed with the lazy-loaded call-logs feature module;
  - tests passed with `22` files and `142` tests;
  - the existing Angular initial bundle budget warning remains;
  - the existing `pusher-js` CommonJS warning remains.
- Stage 12 follow-up:
  - call-log CSV export is available through the tenant API and the Angular/Vue support surfaces;
  - deterministic demo call data now includes at least `1,000` reproducible rows across the previous 30-90 days.
- full backend verification after the call-log slice:
  - `562` passed
  - `0` failed
  - `21` skipped
  - `17682` assertions
  - `952.50s`

Call-log-specific notes:

- Call logs and statistics are implemented.
- Call data is currently produced by tests, seeders, and fake-provider flows.
- No FreeSWITCH service was added.
- No SIP.js behavior was added.
- No real calls were implemented.
- No billing or balance deduction was implemented.

## Stage 12 Navigation and Permissions Follow-up

Follow-up validation on 2026-06-26 audited the tenant navigation chain from RBAC seeding to Angular sidebar visibility.

Verified:

- canonical tenant feature permissions remain unprefixed in the RBAC catalog, backend policies, API payloads, and Angular sidebar checks;
- tenant role-permission pivots are refreshed by the safe demo seeder command without destructive reseeding;
- tenant context payloads return tenant feature permissions for authorized users and limited subsets for restricted roles;
- Angular sidebar translations now resolve `layout.nav.chat` instead of rendering the raw key;
- Vue administration navigation remains unchanged because tenant telephony navigation belongs to Angular.
