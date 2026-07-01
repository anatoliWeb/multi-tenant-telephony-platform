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

Implemented after the initial slice:

- tenant-owned contacts, phones, emails, and tags;
- tenant-scoped contact search, lookup, import, and export;
- tenant-aware contact route binding and isolation coverage.
- tenant-owned extensions and extension credentials;
- tenant-scoped extension-number uniqueness and credential rotation;
- tenant-aware fake-provider endpoint provisioning and provider-state reads.
- tenant-owned call logs and call events;
- tenant-scoped call-history visibility and statistics;
- tenant-scoped call-log CSV export;
- tenant-aware telephony lifecycle recording through the fake provider.
- tenant-scoped navigation permissions returned through `/api/v1/user/tenants`, `/api/v1/user/tenant`, and `/api/v1/user/tenant/switch`.

## Request Contract

Active tenant selection is carried with:

- `X-Tenant-ID: <tenant UUID or slug>`

If a request needs a tenant and the header is missing, the backend fails closed unless the request is on the external chat API and the authenticated actor has exactly one active tenant membership. Ambiguous external-chat tenant selection is rejected.

## Current API

- `GET /api/v1/user/tenants`
- `GET /api/v1/user/tenant`
- `POST /api/v1/user/tenant/switch`
- `GET /api/v1/settings/preload`

The tenant context payload returns:

- `current_tenant_id`
- `platform_permissions`
- `tenant_permissions`
- `permissions` on the current-tenant endpoints, matching `tenant_permissions`

`GET /api/v1/settings/preload` is a public bootstrap endpoint for the frontend
shell. It returns only active frontend settings that are explicitly marked
public and global. Private or backend-only settings must not be exposed there.

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

`TenantBootstrapService` is runtime-only.

It may:

- resolve the current tenant from `X-Tenant-ID`;
- list accessible tenants for the authenticated user;
- validate whether the selected tenant is active and accessible;
- return the active membership for ordinary tenant users;
- identify the protected Platform Admin role through canonical role assignment.

It must not:

- create tenants;
- create memberships;
- backfill missing demo users;
- repair role-permission pivots;
- attach Platform Admin to tenants as a side effect.

Deterministic tenant and membership creation now lives in `Database\Seeders\Support\TenantSeedService` and the seeder layer.

## Seeder Layout

Tenant-aware fixtures are now split by intent:

- `CoreSeeder` seeds shared RBAC and system baseline data;
- `DemoSeeder` seeds deterministic demo tenants, personas, memberships, contacts, extensions, phone numbers, and call logs;
- `TestSeeder` seeds testing-only tenant fixtures;
- `PerformanceSeeder` seeds high-volume tenant data on demand.

The demo and test seeders intentionally use stable identity keys so repeated runs stay predictable and do not rewrite existing passwords.

## Platform Admin Access Model

The canonical Platform Admin account is `platform-admin@test.local`.

Platform Admin access is role-based, not email-based:

- the protected platform `admin` role unlocks the Vue platform shell;
- the same role allows listing every active tenant through `/api/v1/user/tenants`;
- tenant permissions remain empty until a tenant is explicitly selected;
- after selection, tenant permission resolution returns the full tenant permission catalog for that tenant only;
- tenant APIs remain scoped to the selected tenant and still fail closed when no tenant is active.

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

## Contacts Ownership

Contacts extend the same shared-database tenant boundary used by chat.

Rules:

- every `contacts`, `contact_phones`, `contact_emails`, and `contact_tags` row stores `tenant_id`;
- contact writes derive ownership from `TenantContext`;
- cross-tenant route binding fails before controller logic;
- duplicate detection is tenant-scoped;
- the same normalized phone may exist in different tenants without leaking data across lookup APIs.

## Next Step

The next slice should continue applying tenant ownership to the remaining legacy modules and propagate tenant context through the rest of the runtime.

Private per-user contacts remain deferred. The completed `Personal contacts` TODO item refers to tenant-owned contacts representing natural persons, not user-private contact books.

## Frontend Boundary

- Angular is the tenant application and owns tenant feature navigation such as chat, contacts, extensions, phone numbers, and call logs.
- Angular tenant navigation must use `tenant_permissions` only and must not fire tenant feature requests until a tenant is selected.
- Vue remains the platform administration shell and must use `platform_permissions`, not tenant permissions, for admin navigation.
- Vue platform support pages for tenants, contacts, extensions, phone numbers,
  and call logs may reuse tenant-safe `/api/v1/*` endpoints only after an
  explicit tenant selection is active in the support UI.

## Phone Number Ownership

Phone numbers use the same tenant boundary as contacts, chat, and extensions.

- every DID row stores `tenant_id`;
- writes derive ownership from `TenantContext`;
- route binding is tenant-aware;
- the same normalized DID may exist in different tenants for deterministic fixtures;
- assigned users must be active members of the same tenant.

## Call Log Ownership

Call logs extend the same shared-database tenant boundary.

- every `call_logs` row stores `tenant_id`;
- every `call_events` row stores `tenant_id`;
- call-event writes validate that the event tenant matches the parent call log tenant;
- own-call and all-call visibility checks are enforced after tenant scoping, not instead of it;
- same provider call identifiers may exist in different tenants without violating isolation.

## IVR Ownership

IVR menus and options use the same tenant boundary.

- every `ivr_menus` row stores `tenant_id`;
- every `ivr_options` row stores `tenant_id`;
- route evaluation is always tenant-scoped and resolves through `TenantContext`;
- timeout and invalid-input actions stay inside the active tenant;
- self-loops and obvious nested menu loops are rejected before any PBX or media integration stage;
- cross-tenant destinations are rejected instead of being silently normalized;
- real audio playback and real call execution remain out of scope for this slice.
