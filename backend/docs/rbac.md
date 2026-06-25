# RBAC

## Scope Model

The application keeps one RBAC subsystem with two scopes:

- `platform`
- `tenant`

Roles now carry:

- `scope`
- `scope_reference`
- `tenant_id`
- `is_system`
- `is_protected`

Permissions now carry:

- `scope`
- `scope_reference`

`scope_reference` is used to keep platform and tenant catalog entries unique without replacing the existing RBAC tables.

## Resolution

- Platform requests resolve platform-role permissions only.
- Tenant requests resolve tenant-role permissions only for the active `TenantContext`.
- Tenant permission resolution requires an active and `active`-status `TenantMembership` for the current user and tenant.
- Legacy direct user permissions remain in the database for compatibility, but they are not part of the tenant permission resolver.

## Cache

The permission cache is split by scope:

- platform cache keys use the user id and the platform RBAC version;
- tenant cache keys use the tenant id, user id, and tenant RBAC version.

Tenant cache entries are invalidated independently from platform entries.

Verified behavior:

- switching from one tenant to another does not leak cached permission names across tenants;
- tenant permission cache entries are rebuilt from the active tenant context only;
- platform and tenant permission catalogs can now contain the same permission name without colliding in resolution.

## Seeder Coverage

`CoreSeeder` seeds the shared permission catalog and platform roles.

`DemoSeeder`, `TestSeeder`, and `PerformanceSeeder` seed tenant roles per tenant so that tenant-specific role names stay isolated by tenant scope and `scope_reference`.

The seeding flow keeps role and permission identity stable by using `name` plus `scope`, rather than auto-increment ids.

## Middleware

`PermissionMiddleware` understands scoped permission strings:

- `platform.*` uses platform permissions;
- `tenant.*` uses tenant permissions and requires an active tenant context.

Unprefixed checks default to the platform scope for backward compatibility.

## Compatibility

The legacy direct-permission and denied-permission tables stay in place for now.

- They are not exposed in the new tenant-facing permission flow.
- They cannot be used to bypass tenant boundaries.
- Platform session payloads may still include platform-compatible legacy behavior where needed.

## Tenant Chat Boundary

Tenant chat is now explicitly protected against platform-permission bleed-through.

- platform permissions may still protect platform-shell routes and external-chat entry middleware;
- once tenant context is active, tenant chat authorization resolves tenant permissions only;
- active membership without tenant chat permission is insufficient for chat view, send, attachment, webhook-management, and external-message actions;
- cross-tenant isolation regressions cover tenant switching, permission cache separation, tenant chat view denial, and tenant external-message denial.

See `backend/docs/tenant-isolation-tests.md` for the current matrix.

## Contacts Permissions

The Contacts module uses tenant-scoped permissions only.

Implemented permissions:

- `tenant.contacts.view`
- `tenant.contacts.create`
- `tenant.contacts.update`
- `tenant.contacts.delete`
- `tenant.contacts.import`
- `tenant.contacts.export`
- `tenant.contacts.manage_tags`

Verified behavior:

- platform permissions alone do not grant tenant contact access;
- suspended memberships cannot access tenant contacts;
- contact tags cannot be attached across tenants;
- export and import follow the same tenant permission boundary as CRUD.
