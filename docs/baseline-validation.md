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

## Backend Runtime Stabilization

Follow-up validation on 2026-06-30 confirmed that the isolated `saas_testing` bootstrap now loads a stored MySQL schema dump and that the targeted telephony backend suites complete successfully.

Verified commands and results:

- `docker compose exec -T backend php artisan test --env=testing tests/Feature/RingGroups/RingGroupApiTest.php --stop-on-failure` - `PASS`, `2` tests, `38` assertions, `89.50s`
- `docker compose exec -T backend php artisan test --env=testing tests/Feature/RingGroups/RingGroupRoutingServiceTest.php --stop-on-failure` - `PASS`, `3` tests, `11` assertions, `117.31s`
- `docker compose exec -T backend php artisan test --env=testing tests/Feature/CallQueues/CallQueueApiTest.php --stop-on-failure` - `PASS`, `2` tests, `46` assertions, `111.95s`
- `docker compose exec -T backend php artisan test --env=testing tests/Feature/CallQueues/CallQueueRoutingServiceTest.php --stop-on-failure` - `PASS`, `3` tests, `12` assertions, `113.58s`
- `docker compose exec -T backend php artisan test --env=testing tests/Feature/Ivr/IvrApiTest.php --stop-on-failure` - `PASS`, `2` tests, `30` assertions, `126.60s`
- `docker compose exec -T backend php artisan test --env=testing tests/Feature/Ivr/IvrRoutingServiceTest.php --stop-on-failure` - `PASS`, `2` tests, `6` assertions, `114.39s`
- `docker compose exec -T backend php artisan test --env=testing tests/Feature/Seeders/SeederArchitectureTest.php --stop-on-failure` - `PASS`, `5` tests, `98` assertions, `151.72s`
- `docker compose exec -T backend php artisan test --env=testing --filter=RingGroup` - `PASS`, `5` tests, `49` assertions, `128.29s`
- `docker compose exec -T backend php artisan test --env=testing --filter=CallQueue` - `PASS`, `5` tests, `58` assertions, `122.74s`
- `docker compose exec -T backend php artisan test --env=testing --filter=Ivr` - `PASS`, `4` tests, `36` assertions, `119.86s`
- `docker compose exec -T backend php artisan test --env=testing --filter=SeederArchitectureTest` - `PASS`, `5` tests, `98` assertions, `152.69s`
- `docker compose exec -T backend php artisan test --env=testing --filter=TenantAwareRbac` - `PASS`, `6` tests, `14` assertions, `125.52s`

Limitations:

- The full backend suite was not rerun in this follow-up because the task only required the telephony runtime stabilization slice.
- The schema-dump-backed loader depends on the backend image having the MySQL client installed, which is now part of `docker/php/Dockerfile`.
- Local and testing database resets now default `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false` when the flag is not set, so Laravel's schema-dump loader can call the container MySQL client without tripping over the self-signed Docker certificate chain.
- `migrate:fresh --seed` now repopulates the local demo baseline, and `migrate:fresh --seed --env=testing` repopulates the deterministic test fixtures.
- Stage 14 FreeSWITCH support is now scaffolded as an optional Docker profile and was validated with the working image shown below.
- The FreeSWITCH profile uses `servicebots/freeswitch:latest`, keeps the Event Socket bound to localhost, publishes both `5066/tcp` and `7443/tcp` for browser SIP transport, and relies on image defaults instead of a `/etc/freeswitch` bind mount.
- The stable local container name is `multi-tenant-telephony-platform-freeswitch`, and the old generated container can be removed once if it still exists from an earlier run.
- Stage 15.4 demo-directory provisioning now copies the local XML users into the running container and verifies `1001` / `1002` with the FreeSWITCH lookup syntax `user_exists id <user> <domain>`.
- Stage 15.5 keeps the browser SIP domain browser-reachable while allowing the FreeSWITCH directory lookup domain to remain Docker-runtime specific during local provisioning checks.
- Stage 15.6 adds a DB-backed provisioning test harness: XML contract tests cover directory and dialplan output, tenant-isolation security tests keep secrets out of generated XML and logs, and a live smoke script verifies the optional FreeSWITCH container without folding it into the default backend suite.
- Stage 15.7 scaffolds a Laravel-backed directory endpoint that stays local-only, uses an explicit tenant id, and returns XML from DB extensions without guessing tenant identity.
- Stage 15 browser auth now also provisions a local `localhost` directory alias plus a temporary runtime-domain XML copy so the browser-facing SIP domain can authenticate `1001`, `1002`, `2001`, and `2002` without exposing Docker runtime IPs.
- The provisioning flow now also copies a local demo dialplan fixture into the public and default contexts so local `1001 <-> 1002` and `2001 <-> 2002` browser calls can bridge through the runtime realm, and the default context prepends that demo include before the stock `Local_Extension` rules so the live Sofia contact bridge wins before any `bridge(user/...)` fallback.
- Recreating the FreeSWITCH container clears the runtime-copied XML and active SIP registrations, so browser testing must be reprovisioned and re-registered after any `down` or recreate cycle.
- The `localhost` alias and runtime-domain copy must include real password params for browser auth; pointer-only XML is insufficient, and `find_user_xml id <user> <domain>` is the most reliable verification step in this image.
- Laravel contract tests are complete/pass; the live smoke script remains optional/manual and depends on the local Docker runtime.

## Stage 14 Validation

Manual smoke verification on 2026-07-01 confirmed the verified image boot path:

- `docker compose --profile freeswitch pull freeswitch` successfully pulled `servicebots/freeswitch:latest`.
- `docker compose --profile freeswitch up -d freeswitch` created and started the container.
- `docker compose ps` showed the container running.
- If an older generated container name is still present, remove it once with `docker rm multi-tenant-telephony-platform-freeswitch-1` before rerunning the profile.
- `docker compose exec -T freeswitch fs_cli -x "status"` returned `FreeSWITCH ... is ready`.
- `docker compose exec -T freeswitch fs_cli -x "sofia status profile internal"` showed `internal RUNNING` and `external RUNNING`.
- The browser-facing SIP and WSS values remain on `localhost` and `wss://localhost:7443`.
- The optional profile still keeps Event Socket access bound to localhost.

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
- Stage 13.3 IVR foundation is implemented in code, but the current report still
  needs fresh automated and browser verification before it can be treated as a
  fully revalidated milestone alongside the earlier baseline work.
- Angular SIP.js softphone foundation is now present.
- Stage 15.2 adds a local-demo credential gate for browser registration in
  local development only.
- Stage 15.3 wires a local-registration attempt and local extension-call flow,
  but end-to-end browser verification remains a manual follow-up because local
  WSS/TLS trust still needs confirmation on the target machine. Local demo
  mode can fall back to `ws://localhost:5066` when the browser rejects the
  self-signed WSS certificate.
- The local FreeSWITCH demo bridge now resolves the live Sofia contact before
  bridging and returns `480 Temporarily Unavailable` when the destination is
  not registered, which avoids masking contact lookup problems as media
  negotiation failures.
- The current call-control stabilization slice keeps the remote audio element
  unmuted and surfaces media diagnostics for autoplay, playback, and
  connection-state failures.
- Docker-side FreeSWITCH readiness was also rechecked on 2026-07-01: the
  runtime reports `WS-BIND-URL` and `WSS-BIND-URL`, and demo users `1001` and
  `1002` resolve in the runtime domain `172.18.0.12`; the default tenant now
  exposes the `1001/1002` selector pair while the secondary tenant keeps
  `2001/2002`; live browser registration
  remains partial in this workspace because the browser-control bridge is
  unavailable here.
- Vue Admin SIP.js softphone remains a planned follow-up slice only.

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
