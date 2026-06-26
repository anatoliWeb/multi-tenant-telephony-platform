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

### TestSeeder

Seeds the test-only baseline used by feature tests:

- deterministic tenants;
- active and suspended memberships;
- platform and tenant role assignments;
- multi-tenant coverage for isolation tests;
- minimal deterministic contact fixtures.
- minimal deterministic extension fixtures.
- minimal deterministic phone-number fixtures.

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
