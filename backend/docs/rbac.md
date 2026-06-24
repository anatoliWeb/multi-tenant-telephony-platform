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
