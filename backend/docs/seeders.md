# Seeders

This project now separates seeding into four explicit layers:

- `CoreSeeder` for mandatory shared system data;
- `DemoSeeder` for deterministic local/demo tenant data;
- `TestSeeder` for test-only tenant fixtures;
- `PerformanceSeeder` for explicit high-volume data generation.

## Safety Rules

- Core data is designed to be safe to rerun.
- Demo data is environment-controlled and refuses production.
- Test data refuses non-testing environments and asserts the safe test database.
- Performance data requires explicit invocation and refuses production unless `--allow-production` is provided.
- Seeder identity uses stable keys such as slug, email, scope, and tenant combination.
- Existing user passwords are only set on first create.

## Command Surface

```bash
php artisan app:seed-core
php artisan app:seed-demo
php artisan app:seed-performance --tenants=3 --users=150
```

Use `--force` to skip interactive confirmation on the seeder commands.
Use `--allow-production` only when performance seeding must run in production-like conditions.

Canonical safe RBAC refresh for the running development stack:

```bash
docker compose exec -T backend php artisan app:seed-demo --force
```

This command is idempotent for the demo baseline, refreshes tenant role-permission pivots, and avoids destructive resets such as `migrate:fresh`.

Canonical clean development reset:

```bash
docker compose exec -T backend php artisan migrate:fresh --force
docker compose exec -T backend php artisan app:seed-core --force
docker compose exec -T backend php artisan app:seed-demo --force
docker compose exec -T backend php artisan optimize:clear
```

Testing reset note:

- run test-database resets and Laravel test suites sequentially, not in parallel;
- the shared `saas_testing` database is guarded for safety but still assumes one
  migration lifecycle at a time;
- if a test run aborts mid-migration, rerun `composer test:preflight` before the
  next targeted or full suite.
- the backend test image now includes the MySQL client so the stored schema dump
  can be loaded quickly for isolated `saas_testing` runs without touching dev or
  production data.

## Seeder Responsibilities

### CoreSeeder

Seeds shared system data only:

- permission catalog entries for platform and tenant scopes;
- platform roles;
- platform role-permission mappings;
- shared system settings;
- translation fixtures.

### DemoSeeder

Seeds a deterministic demo baseline for local development:

- platform admin/support users;
- default, secondary, and suspended tenants;
- tenant owners, admins, managers, analysts, agents, and read-only personas;
- multi-tenant membership scenarios;
- suspended membership and suspended tenant examples;
- custom tenant role coverage.
- tenant role-permission pivots for every seeded system role.
- RBAC cache invalidation after catalog and pivot refresh.

Chat demo data is tenant-specific, deterministic, and idempotent. Demo conversations, participants, messages, webhook endpoints, and webhook deliveries are created under the target tenant so the same demo users can have separate chat histories in different tenants.

The live Stage 7 backfill validated that existing demo chat rows can be migrated forward without data loss before tenant ownership is enforced as `NOT NULL`.

Contacts demo data is also tenant-specific, deterministic, and idempotent.

It includes:

- different contacts per tenant;
- one normalized phone reused safely across two tenants;
- multiple phone numbers on selected contacts;
- tag coverage;
- archived contact coverage.

Extensions demo data is tenant-specific, deterministic, and idempotent.

It includes:

- the same extension number reused safely across different tenants;
- valid assignment to tenant members only;
- fake-provider-backed provisioned metadata;
- encrypted credential fixtures without stored plaintext secrets.

Call log demo data is tenant-specific, deterministic, and idempotent.

It includes:

- inbound answered, inbound missed, outbound answered, outbound failed, and internal completed calls;
- contact-linked and unknown external-party scenarios;
- append-only lifecycle events for seeded calls;
- the same provider call id reused safely across two tenants for isolation coverage.
- reproducible high-volume volume fixtures that generate at least 1,000 total call logs for Stage 12 validation;
- deterministic timestamps spread across the previous 30-90 days so exports and charts remain stable on rerun.

IVR demo data is also tenant-specific, deterministic, and idempotent.

It includes:

- at least two IVR menus per active demo tenant;
- a main business-hours IVR;
- an after-hours IVR;
- options that route to tenant-local demo ring groups and call queues;
- timeout and invalid-input actions that stay within the active tenant;
- stable digits and priorities so repeated seeding stays predictable.

### TestSeeder

Seeds the test-only baseline used by feature tests:

- deterministic tenants;
- active and suspended memberships;
- platform and tenant role assignments;
- multi-tenant coverage for isolation tests;
- tenant role-permission pivots for seeded tenant roles;
- minimal deterministic contact fixtures.
- minimal deterministic extension fixtures.
- minimal deterministic phone-number fixtures.
- minimal deterministic call-log fixtures.

## Boundary Rule

Seeders and seed services own deterministic data creation and repair.

- `TenantSeedService` owns base tenants and legacy membership backfill used during schema setup.
- `TenantDemoSeedService` owns demo personas, memberships, role assignments, and tenant demo fixtures.
- `TenantBootstrapService` is read-only and must not create or repair data.
- IVR seed data belongs in `TenantDemoSeedService` so demo routing graphs stay repeatable and safe for screenshots, tests, and tenant-specific fixtures.

### PerformanceSeeder

Seeds high-volume data in batches:

- deterministic performance tenants;
- chunked user inserts;
- tenant memberships;
- tenant role assignments;
- repeatable tenant/email identity keys.

## Idempotency Notes

- Rerunning `CoreSeeder` should not change the catalog shape.
- Rerunning `DemoSeeder` should not duplicate demo tenants or users.
- Rerunning `DemoSeeder` should not duplicate chat conversations or messages.
- Rerunning `DemoSeeder` and `TestSeeder` should resync canonical tenant role grants if the catalog changes.
- Rerunning `TestSeeder` should not leak fixtures into production.
- Rerunning `PerformanceSeeder` should reuse the same tenant and user identity keys.

## Related Docs

- `backend/docs/commands.md`
- `backend/docs/docker.md`
- `backend/docs/multi-tenancy.md`
- `backend/docs/rbac.md`
- `backend/docs/phone-numbers.md`

## DID Demo Fixtures

`DemoSeeder` now creates deterministic tenant-aware DID inventory:

- Tenant A: `+15550001001` primary for owner, `+15550001002` secondary for owner, `+15550001003` primary for agent, `+15550001999` unassigned.
- Tenant B: `+15550001001` primary for the tenant owner.

`TestSeeder` creates a duplicate-number-across-tenants fixture for isolation tests.
