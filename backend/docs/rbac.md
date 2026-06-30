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
- Tenant permission resolution requires an active and `active`-status `TenantMembership` for ordinary tenant users.
- Platform Admin is the one explicit exception: after selecting an active tenant, tenant permission resolution returns the canonical tenant catalog for that tenant without creating a membership row.
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

`PermissionMiddleware` resolves permissions by the active RBAC scope, not by a string prefix.

- platform routes still use platform-resolved permissions;
- unprefixed permission names resolve against tenant scope automatically when `TenantContext` is active;
- tenant routes now use the canonical permission names from the tenant catalog;
- tenant authorization requires an active tenant context, while membership enforcement stays inside tenant permission resolution instead of scattered route exceptions.

Canonical tenant feature names:

- `chat.view`
- `chat.conversations.view`
- `contacts.view`
- `contacts.create`
- `contacts.update`
- `contacts.delete`
- `contacts.import`
- `contacts.export`
- `contacts.manage_tags`
- `extensions.view`
- `extensions.create`
- `extensions.update`
- `extensions.delete`
- `extensions.manage_credentials`
- `phone_numbers.view`
- `phone_numbers.create`
- `phone_numbers.update`
- `phone_numbers.delete`
- `phone_numbers.assign`
- `phone_numbers.set_primary`
- `phone_numbers.provision`
- `phone_numbers.release`
- `call_logs.view`
- `call_logs.view_own`
- `call_logs.view_all`
- `call_logs.export`
- `call_logs.view_statistics`

Canonical platform-admin support names exposed in Vue Admin:

- `tenants.view`
- `contacts.view`
- `extensions.view`
- `phone_numbers.view`
- `call_logs.view`
- `call_logs.export`

These platform permissions control Vue Admin navigation and support pages.
They do not replace tenant-scoped authorization inside the tenant API. The
support UI must still select an active tenant and send that tenant context on
every tenant-scoped request.

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

- `contacts.view`
- `contacts.create`
- `contacts.update`
- `contacts.delete`
- `contacts.import`
- `contacts.export`
- `contacts.manage_tags`

Verified behavior:

- platform permissions alone do not grant tenant contact access;
- suspended memberships cannot access tenant contacts;
- contact tags cannot be attached across tenants;
- export and import follow the same tenant permission boundary as CRUD.

## Extensions Permissions

The Extensions module also uses tenant-scoped permissions only.

Implemented permissions:

- `extensions.view`
- `extensions.create`
- `extensions.update`
- `extensions.delete`
- `extensions.manage_credentials`

Verified behavior:

- platform permissions alone do not grant tenant extension access;
- suspended memberships cannot access tenant extensions;
- assigned users and assigned contacts must belong to the active tenant;
- credential rotation stays behind the dedicated credential-management permission;
- cross-tenant extension identifiers fail closed.

## Phone Numbers Permissions

The DID module uses tenant-scoped permissions only.

Implemented permissions:

- `phone_numbers.view`
- `phone_numbers.create`
- `phone_numbers.update`
- `phone_numbers.delete`
- `phone_numbers.assign`
- `phone_numbers.set_primary`
- `phone_numbers.provision`
- `phone_numbers.release`

Verified behavior:

- platform permissions alone do not grant tenant DID access;
- suspended, invited, and removed memberships cannot receive a DID assignment;
- cross-tenant user identifiers fail closed;
- primary DID changes remain tenant-local.

## Call Logs Permissions

The Call Logs module uses tenant-scoped permissions only.

Implemented permissions:

- `call_logs.view`
- `call_logs.view_own`
- `call_logs.view_all`
- `call_logs.export`
- `call_logs.view_statistics`

Verified behavior:

- `call_logs.view_own` is limited to rows where the active user is the caller or callee user;
- `call_logs.view_all` unlocks tenant-wide list and detail visibility;
- statistics follow the same own-vs-all boundary;
- active membership is required for ordinary tenant users;
- platform permissions alone do not grant tenant call-log access.

## IVR Permissions

The IVR module also uses tenant-scoped permissions only.

Implemented permissions:

- `ivr.view`
- `ivr.create`
- `ivr.update`
- `ivr.delete`
- `ivr.manage_options`
- `ivr.test_route`

Verified behavior:

- platform permissions alone do not grant tenant IVR access;
- suspended memberships cannot manage tenant IVR menus;
- IVR options stay tenant-local and cannot target another tenant's extensions, queues, ring groups, or menus;
- route testing is tenant-scoped and returns a dry-run plan instead of executing media or PBX actions.
