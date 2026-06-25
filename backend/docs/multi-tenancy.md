# Multi-Tenancy

## Slice 1 Status

Shared-database multi-tenancy foundation is now implemented in the backend and partially wired into the frontend clients.

Implemented in this slice:

- `Tenant` and `TenantMembership` persistence
- request-scoped `TenantContext`
- tenant resolution middleware
- tenant switching endpoint
- initial tenant membership bootstrap/backfill
- initial request logging enrichment
- initial isolation tests
- cross-tenant isolation regressions for tenant switching, tenant chat access, external integrations, and route binding
- tenant-owned chat schema enforcement and live backfill validation

Not yet implemented:

- tenant ownership across notifications and activity
- tenant-aware RBAC split
- tenant context propagation to jobs and broadcasts
- tenant-specific telephony features

## Request Contract

Active tenant selection is carried with:

- `X-Tenant-ID: <tenant UUID or slug>`

If a request needs a tenant and the header is missing, the backend fails closed unless the request is on the external chat API and the authenticated actor has exactly one active tenant membership. Ambiguous external-chat tenant selection is rejected.

## Current API

- `GET /api/v1/user/tenants`
- `GET /api/v1/user/tenant`
- `POST /api/v1/user/tenant/switch`

## Data Model

### Tenant

Fields:

- `id` UUID primary key
- `name`
- `slug`
- `status`
- `timezone`
- `locale`
- `currency`
- `settings`
- `activated_at`
- `suspended_at`
- timestamps

### TenantMembership

Fields:

- `id` UUID primary key
- `tenant_id`
- `user_id`
- `status`
- `invited_by`
- `invited_at`
- `accepted_at`
- `activated_at`
- `suspended_at`
- timestamps

## Bootstrap Strategy

The current bootstrap service creates:

- one default active tenant;
- one second active tenant;
- one suspended tenant;
- active memberships for non-platform users;
- one dual-tenant demo user;
- one suspended membership example.

Platform-only users are identified through the existing `admin` role and are not automatically attached to tenant memberships.

## Seeder Layout

Tenant-aware fixtures are now split by intent:

- `CoreSeeder` seeds shared RBAC and system baseline data;
- `DemoSeeder` seeds deterministic demo tenants and personas;
- `TestSeeder` seeds testing-only tenant fixtures;
- `PerformanceSeeder` seeds high-volume tenant data on demand.

The demo and test seeders intentionally use stable identity keys so repeated runs stay predictable and do not rewrite existing passwords.

## Chat Ownership

Chat conversations are tenant-owned and act as the root boundary for related messages, participants, attachments, read state, typing, presence, webhook records, and external chat deliveries.

Key rules:

- conversation creation resolves the active tenant from `TenantContext`;
- message rows inherit the conversation tenant;
- attachment storage paths include the tenant UUID;
- chat route binding fails closed outside the active tenant;
- cross-tenant chat access returns a safe not-found or forbidden response instead of leaking metadata.
- live development migration/backfill validation completed without chat data loss;
- chat ownership columns are database-enforced as `NOT NULL` after backfill;
- chat integrity can be audited with `php artisan chat:verify-tenant-integrity`.

## Isolation Verification

Latest hardening added:

- tenant route model binding now honors `X-Tenant-ID` before controller execution, so tenant-owned resources resolve correctly and still fail closed outside the selected tenant;
- authenticated tenant switching now returns the same safe `403 Tenant access denied` response for unknown and inaccessible tenant identifiers;
- ordinary tenant chat and tenant external-message flows no longer accept platform chat permissions as a bypass for tenant-scoped permissions;
- authenticated external chat rate limiting now keys by actor plus explicit tenant header to avoid cross-tenant collisions for the same user token.

Coverage status:

- dedicated regression suite: `Tests\Feature\Tenancy\Isolation\*`;
- full matrix and current gaps: `backend/docs/tenant-isolation-tests.md`;
- full backend verification: `516` passed, `0` failed, `21` skipped, `16402` assertions.

See `backend/docs/chat.md` for the chat-specific runtime summary.

## Next Step

The next slice should continue applying tenant ownership to the remaining legacy modules and propagate tenant context through the rest of the runtime.
