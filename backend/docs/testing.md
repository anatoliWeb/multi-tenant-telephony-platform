# Backend Testing Lifecycle (Local + CI)

## Why targeted runs were slow
- Most feature suites use `RefreshDatabase`.
- Each separate `php artisan test --filter=...` command starts a new PHP process.
- New test process means fresh Laravel bootstrap + migration lifecycle for testing DB.
- Running many small filtered commands in sequence causes repeated cold-start overhead.

## Safety model
- Tests are pinned to `APP_ENV=testing` and testing DB (`saas_testing`) by:
  - `phpunit.xml` env values
  - `tests/TestCase.php` bootstrap env enforcement
  - `TestingDatabaseGuard` fail-fast protection
- Do not run tests in parallel against the same shared `saas_testing` database.

## Memory model
- PHPUnit config sets `memory_limit=512M` for tests.
- Composer test scripts also run with `php -d memory_limit=512M`.
- This avoids intermittent `128M` fatal errors in larger Pest/Scramble/OpenAPI runs.

## Recommended local workflow

### 1. Preflight once per session
```bash
composer test:preflight
```

### 2. Run grouped domain commands (single process per domain)
```bash
composer test:openapi
composer test:chat
composer test:api
composer test:auth
```

Using one grouped command per domain is faster than launching many tiny filtered commands one-by-one.

### 3. Full suite
```bash
composer test
```

## API Test Strategy

- Use `ApiContractSmokeTest` for fast API-level smoke coverage across health, auth, permissions, response envelopes, pagination metadata, validation errors, safe 404s, and permission-aware docs access.
- Use `OpenApiRouteContractTest` and `OpenApiResponseEnvelopeTest` for route/spec/envelope contract checks.
- Use focused Security tests for rate limiting, headers, validation hardening, token safety, and realtime channel authorization instead of duplicating those assertions in API smoke tests.
- Use domain suites such as `Chat`, `Notification`, `UsersApi`, and `ActivityApi` for lifecycle-specific behavior.
- Run `composer test:api` or `php -d memory_limit=512M artisan test --filter=Api --stop-on-failure` before release checks when time allows.
- Prefer targeted API filters locally if the full `Api` filter is slow, but do not mark API infrastructure work complete until the targeted contract tests pass.

## Auth Test Strategy

- Use `AuthContractTest` for consolidated auth contract coverage across Vue Admin session-first auth and bearer token fallback flows.
- Keep session tests focused on login, `/api/v1/auth/session/me`, logout, invalid credentials, and safe validation/authentication errors.
- Keep bearer tests focused on `/api/v1/auth/login` or `/api/v1/auth/token`, `/api/v1/auth/me`, logout revocation, revoked token denial, and standardized `401`/`422` envelopes.
- Use `SecurityTokenSecurityTest` for deeper token hashing, one-time reveal, scope, revocation, and no-plain-token storage guarantees.
- Use `SecurityRateLimitingTest` for auth throttling and safe `429` behavior instead of duplicating rate-limit loops in auth contract tests.
- Use `OpenApiAuthEndpointsTest` for auth documentation/spec coverage.
- Frontend auth store/service behavior is covered by Vue Admin npm tests and should be expanded under the separate frontend integration testing task.

## RBAC Test Strategy

- Use `RbacContractTest` as a consolidated RBAC contract suite for permission foundation, role coverage, safe `401/403/200` access behavior, and middleware/gate contracts.
- Keep permission seeding checks lightweight and focused on core permissions (`api.docs.view`, `api.docs.view.full`, `system.monitoring`, and chat/RBAC-critical keys).
- Use route middleware contract assertions to verify `auth:sanctum` + permission middleware on users/roles/permissions endpoints.
- Use cache-version checks (not full cache internals) to verify role/user permission changes invalidate effective-permission cache through version bumps.
- Keep docs access permission behavior in `OpenApiDocsAccessTest`; only add lightweight gate/middleware checks in RBAC contract tests.
- Keep chat-specific RBAC lifecycle behavior in chat suites (for example `ChatRbacPermissionTest`) to avoid duplicating conversation/message flows here.

## Queue Test Strategy

- Use `QueueContractTest` for consolidated queue infrastructure contracts: env-driven queue connection, Redis support, failed jobs tracking, priority queue names, supervisor/Horizon queue order, and queue command documentation.
- Keep critical job contract checks lightweight and static in queue contract tests (for example `DeliverChatWebhookJob` queue/tries/backoff/timeout and safe serialized state).
- Use `QueueLoggingTest` for runtime safe logging assertions and sensitive key stripping checks.
- Use `QueuePerformanceOptimizationTest` for queue performance baseline and worker runtime flags.
- Keep webhook delivery lifecycle, retry scheduling, and external delivery behavior in dedicated chat suites (`ChatWebhookDelivery*`) to avoid duplicated queue-lifecycle logic.
- Keep queue/realtime dispatch-path behavior in existing domain suites (for example `RealtimeQueueTest`) and only add queue-contract assertions here.

## Realtime Test Strategy

- Use `RealtimeContractTest` as a consolidated realtime contract suite for channel authorization matrix, presence/global channel access rules, event queue/channel naming contracts, and diagnostics documentation checks.
- Keep chat payload-depth safety checks in dedicated suites (`ChatRealtimeSafePayloadTest`, `ChatPresenceSafePayloadTest`) to avoid duplicating heavy lifecycle assertions.
- Keep security-focused channel denial/access behavior in `SecurityRealtimeChannelAuthorizationTest`.
- Keep realtime logging behavior and sanitized denied-auth context in `RealtimeLoggingTest`.
- Keep broader chat realtime lifecycles in `ChatRealtimeEventsTest` and presence lifecycle behavior in `ChatPresenceChannelTest`.
- Browser-level websocket/e2e checks (WS/EV/ON/PG UI behavior) belong to frontend integration testing and should stay outside backend feature suites.

## Frontend Integration Test Strategy

- Use Vue Admin Vitest integration specs for auth-shell contracts (unauthenticated hides protected navigation, authenticated shell rendering, stale token/session cleanup, and logout state reset) without browser e2e dependencies.
- Keep permission-aware navigation coverage in Vue layout/page specs: API docs shortcut visibility, users/roles/permissions menu visibility, and hidden-item rendering behavior.
- Keep API client and auth service behavior in frontend unit/integration tests (Bearer header attachment when token exists, safe 401 reset behavior, no token/secret rendering in error states).
- Keep realtime diagnostics coverage in Vue specs for WS/EV/ON/PG metric rendering and safe UI output without sensitive token/secret text.
- Use Angular dashboard tests as lightweight smoke + integration checks for app shell rendering, router outlet presence, API client base URL behavior, and RBAC guard behavior.
- Keep browser-driven flows (full login redirects, live websocket session behavior, cross-tab presence, and deep navigation timing) in a future dedicated e2e track (Playwright/Cypress), not in the current frontend integration foundation.

## Final UI Cleanup Checks

- Keep permission-aware navigation and cards hidden when permissions are missing.
- Keep API docs shortcuts pinned to `/docs/api/portal` in sidebar and dashboard shortcuts.
- Keep realtime diagnostics labels (WS/EV/ON/PG) visible as operational status, not debug junk.
- Keep loading/empty/error UI states readable and localized through i18n keys where practical.
- Keep rendered UI free from token/secret/debug output.
- Run frontend tests and builds for both Vue Admin and Angular Dashboard after UI polish changes.

## Debug Log Cleanup Policy

- Temporary debug helpers (`dd`, `dump`, `ray`, `var_dump`, `print_r`) are prohibited in runtime backend directories.
- Temporary frontend debug artifacts (`console.log`, `console.debug`, `debugger`) are prohibited in committed production source files.
- Structured backend observability logs (request/error/queue/realtime/monitoring) remain allowed and should not be removed by cleanup.
- Sensitive values (token/secret/password/authorization/cookies) must never be echoed or logged in Docker/scripts or application source.
- Use configured structured logging services and feature-level tests instead of ad-hoc debug output.

## Code Comment Standard

- Technical PHPDoc/code comments must be in English.
- Prefer comments that explain WHY (policy, tradeoff, safety, cache/versioning, retry/backoff), not obvious WHAT.
- Keep security-sensitive decisions documented in the code path that enforces them.
- Keep cache invalidation/versioning and queue retry/backoff rationale documented in critical services/jobs.
- Remove noisy/redundant comments that simply restate code.
- Never include real token/secret/password values in comments.

## PHPDoc Strategy For Key Services

- Add PHPDoc only for non-obvious contracts and side effects (security filtering, caching, retries, permission gates, query batching).
- Prefer method-level contract notes for structured arrays and boundary behavior over repetitive type-only comments.
- Keep queue/webhook/OpenAPI/realtime methods documented where runtime behavior depends on policy flags or versioned invalidation.
- Avoid mechanical PHPDoc on trivial getters/setters and obvious one-line methods.

## Type Hint Audit Policy

- Add return types and scalar/nullable hints only for stable contracts where Laravel runtime behavior is predictable.
- Do not force typing across dynamic boundaries (Eloquent magic attributes/relations, framework-driven flexible signatures).
- Keep middleware signatures compatible with `Request`, `Closure $next`, and HTTP response return behavior.
- Prefer explicit return types for key services and complex helpers (`array`, `bool`, `int`, `string`, `void`) and keep structured-array detail in PHPDoc.
- Avoid mass typing refactors; use targeted low-risk changes and cover them with focused static tests.

## Naming Consistency Policy

- Use `OpenAPI` for generated specifications and `Swagger UI` only for the UI layer.
- Use `API docs portal` for `/docs/api/portal` and `filtered OpenAPI spec` for `/docs/api.filtered.json`.
- Describe permission-scoped docs behavior as `permission-aware docs`.
- Use `Realtime` for the feature area, `Reverb` for Laravel runtime, and `WebSocket` for transport naming.
- Use `RBAC` for the roles/permissions system across backend, frontend, tests, and docs.
- Use `queue-worker` when naming the Docker service and `queue worker` for the general concept.
- Keep canonical frontend names consistent: `Vue Admin` and `Angular Dashboard`.
- Keep permission names, queue names, route names, and event names stable unless an explicit migration task exists.

## Folder Structure Validation Policy

- Keep repository boundaries explicit: root orchestration/docs, backend Laravel runtime under `backend/`, Angular Dashboard under `frontend/`, and Docker configs under root `docker/` + compose files.
- Keep backend technical docs under `backend/docs` and OpenAPI-focused docs under `backend/docs/api`.
- Keep Vue Admin source under `backend/resources/js` and avoid backend PHP/runtime files inside frontend trees.
- Keep Angular Dashboard source under `frontend/src/app` with feature/core/api/auth/i18n separation.
- Use static guards to detect obvious misplaced files (`.php` under Vue source, `.vue/.ts` under backend app, markdown docs under backend app except intentional placeholders).
- Root/frontend structure checks may skip in backend containers when those mounts are unavailable; backend structure checks should always run.

## Architecture Cleanup Guard

- `ArchitectureCleanupTest` verifies key markdown links across architecture/security/monitoring/testing/deployment docs.
- Guard enforces modular monolith positioning and prevents false claims that microservices are already implemented.
- Guard checks dependency boundaries against controller-to-controller and service-to-controller coupling.
- Guard checks `TODO.md` for a single `Phase 23` block to prevent duplicate polish tracking sections.

## When to use `migrate:fresh`
- CI/full clean validation: acceptable.
- Local targeted reruns: avoid manual `migrate:fresh` before every command.
- `RefreshDatabase` already handles isolated test state inside each run.

## Notes on occasional DB deadlocks/table lifecycle issues
- Prefer sequential runs only.
- Avoid overlapping test processes/containers on the same DB.
- If a run aborts unexpectedly, rerun the same suite once after `composer test:preflight`.
