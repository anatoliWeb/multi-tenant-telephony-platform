# Multi-Tenancy Implementation Plan

## Purpose

This document defines the concrete implementation plan for the approved shared-database multi-tenancy model.

Slice 1 of the foundation is now implemented in the backend:

- `Tenant` and `TenantMembership` persistence exists;
- request-scoped tenant context exists;
- tenant switching exists;
- initial backfill/seed logic exists;
- initial isolation tests are being added.

## Approved Model

- Shared database.
- Tenant-owned rows contain `tenant_id`.
- Users may belong to multiple tenants.
- Memberships connect users to tenants.
- `TenantContext` stores the active tenant.

## 2.5 Access Control Scope

The existing RBAC implementation is being adapted rather than replaced.

- platform permissions continue to protect the admin shell;
- tenant permissions are resolved from the active tenant context;
- legacy direct user permissions remain only as compatibility data;
- new tenant-facing authorization does not rely on the legacy direct-permission flow.

## 1. Proposed Entities

### Tenant

Recommended fields:

- `id`
- `uuid`
- `name`
- `slug`
- `status`
- `timezone`
- `locale`
- `currency`
- `settings`
- `activated_at`
- `suspended_at`
- `created_at`
- `updated_at`

Recommended behavior:

- one tenant can own many users through memberships;
- one tenant can own many conversations, notifications, logs, and settings rows;
- tenant status must gate access to tenant-scoped data.

### TenantMembership

Recommended fields:

- `id`
- `tenant_id`
- `user_id`
- `status`
- `invited_by`
- `invited_at`
- `accepted_at`
- `activated_at`
- `suspended_at`
- `created_at`
- `updated_at`

Role handling:

- do not add a single role column if users may have multiple roles;
- assign tenant roles through a separate membership-role relation or an equivalent pivot;
- keep platform roles separate from tenant roles.

## 2. Tenant Context Resolution

Resolution order is fail-closed.

### Authenticated Angular request

1. Read the authenticated user.
2. Resolve the active tenant from the selected membership in `TenantContext`.
3. If the request specifies a tenant switch, validate that the user belongs to that tenant and that the membership is active.
4. If tenant context cannot be resolved, reject the request.

### Authenticated Vue platform request

1. Read the authenticated platform user.
2. Resolve platform permissions first.
3. If the route targets a specific tenant, validate the tenant explicitly.
4. If the route is platform-only, leave tenant context unset.
5. If a tenant-scoped route lacks a valid tenant, reject the request.

### API token

1. Read the authenticated token owner.
2. Resolve tenant binding from the token context or tokenable ownership.
3. If the token is unbound and the route is tenant-scoped, reject the request.
4. If the token is invalid or inactive, reject the request.

### Webhook / integration request

1. Read the integration credential.
2. Resolve the bound tenant or provider connection.
3. Validate signature, scope, and status.
4. Reject the request if tenant context is missing or inactive.

### Queue job

1. Restore the serialized tenant context from the job payload.
2. Validate that the tenant still exists and is active.
3. Fail the job if the tenant context is missing or invalid.

### Scheduled command

1. Read the scheduled tenant target if one is defined.
2. If the command is tenant-scoped, iterate only over valid active tenants.
3. If the command needs a tenant but none is provided, fail closed.

### Broadcast channel authorization

1. Read the authenticated user.
2. Resolve the active tenant from the request or membership context.
3. Validate tenant membership and any module permission requirements.
4. Reject the channel if tenant context is missing or invalid.

## 3. Fail-Closed Behavior

If tenant context is missing or invalid:

- do not load tenant-owned data;
- do not join tenant broadcast channels;
- do not enqueue tenant-scoped side effects;
- do not fall back to a default tenant silently;
- return `403` for authenticated but unauthorized access and `401` for missing authentication when appropriate.

## 4. Data Scoping Matrix

| Existing entity | Ownership | Required change |
| --- | --- | --- |
| User | Global identity | Add membership relation |
| Role | Platform or tenant | Add scope/type and separate platform vs tenant assignment paths |
| Permission | Global catalog | Add platform/tenant category metadata |
| Conversation | Tenant-owned | Add tenant ownership |
| Message | Tenant-owned through conversation, with direct scoping column | Add `tenant_id` directly and keep it synchronized from the conversation on write |
| Notification | Tenant-aware where applicable | Add tenant context |
| ActivityLog | Tenant-aware | Add tenant context |
| Settings | Platform or tenant | Separate scope |
| Translation | Platform or tenant | Define override rules |

## 5. Message Tenant Strategy

`Message` should contain `tenant_id` directly.

Reasoning:

- the approved model says tenant-owned rows contain `tenant_id`;
- direct scoping keeps message queries and policies simple;
- broadcast, cache, and export filters can use a single tenant key;
- fail-closed checks become easier to enforce at the message layer.

Implementation rule for later:

- populate `message.tenant_id` from the owning conversation at write time;
- validate that the message tenant matches the conversation tenant;
- never allow a message to drift across tenants.

## 6. Implementation Phases

### Phase 1: tenant primitives

- create `Tenant` and `TenantMembership` models and persistence;
- introduce `TenantContext`;
- add tenant switching semantics;
- add tenant-aware seed data.

Status:

- completed backend slice: tenant models, persistence, request context, middleware, switch API, bootstrap service, seed wiring, and request-log enrichment;
- remaining work: client-side context selection, tenant ownership propagation, isolation coverage, and tenant-aware runtime propagation.

### Phase 2: data ownership

- add tenant ownership to conversations, messages, notifications, activity logs, and relevant settings;
- update route model binding and query scopes;
- add tenant-aware cache keys and broadcast channels.

### Phase 3: authorization and access control

- separate platform roles from tenant roles;
- separate platform permissions from tenant permissions;
- enforce tenant ownership in policies and middleware;
- prevent cross-tenant reads, writes, exports, and realtime access.

Current slice status:

- platform role and permission scoping has been added to the schema and resolver;
- tenant-scoped permission resolution is available through tenant context payloads and now requires an active tenant membership;
- tenant permission cache isolation is covered by regression tests that switch between tenants cleanly;
- tenant role-management APIs remain a deferred slice.

### Phase 4: operational propagation

- propagate tenant context to jobs, listeners, and scheduled commands;
- update monitoring and queue diagnostics;
- add platform admin visibility for tenant lifecycle and suspension.

### Phase 5: verification

- add cross-tenant isolation tests;
- add tenant-aware chat and notification tests;
- verify queue and broadcast context safety;
- document the new runtime behavior.

Verification status:

- the complete backend suite passes after the tenant permission isolation fix;
- the RBAC tenant-resolution regression is covered by `TenantAwareRbacTest`;
- platform-scope permission fixtures in auth contract tests now explicitly avoid tenant catalog collisions.

## 7. Acceptance Criteria

The multi-tenancy phase is complete only when:

- tenant context is resolved consistently;
- cross-tenant access is blocked by default;
- platform and tenant roles are separated;
- all tenant-owned data is scoped correctly;
- jobs and broadcasts preserve tenant context;
- the test suite verifies the isolation rules.
