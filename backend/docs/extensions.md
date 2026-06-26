# Extensions

## Scope

The Extensions module is now implemented as a tenant-owned telephony directory slice.

Implemented now:

- tenant-owned `Extension` records;
- tenant-owned `ExtensionCredential` records;
- tenant-scoped extension-number normalization and uniqueness;
- assignment to tenant users and tenant contacts;
- fake-provider-backed provisioning and provider-state synchronization;
- one-time credential creation and rotation;
- tenant-scoped APIs, policies, resources, and seed data;
- Angular tenant UI for list, detail, edit, assignment, status changes, provisioning, and credential rotation.

Not implemented:

- real SIP registration;
- FreeSWITCH adapter support;
- SIP.js integration;
- real calls, DIDs, call logs, recordings, queues, IVR, or billing.

## Ownership

- every extension belongs to exactly one tenant;
- ownership is derived from `TenantContext`;
- client input cannot override `tenant_id`;
- route model binding and controller checks fail closed outside the active tenant;
- extensions cannot be moved between tenants through ordinary CRUD.

## Data Model

### Extension

Fields:

- `id`
- `uuid`
- `tenant_id`
- `number`
- `label`
- `status`
- `provisioning_status`
- `registration_status`
- `assigned_user_id`
- `assigned_contact_id`
- `endpoint_key`
- `provider_name`
- `provider_resource_id`
- `credential_username`
- `last_provisioned_at`
- `created_by`
- `updated_by`
- `metadata`
- timestamps

Indexes and constraints:

- unique `uuid`
- unique `(tenant_id, number)`
- unique `(tenant_id, endpoint_key)`
- index `(tenant_id, status)`
- index `(tenant_id, assigned_user_id)`
- index `(tenant_id, assigned_contact_id)`

### ExtensionCredential

Fields:

- `id`
- `tenant_id`
- `extension_id`
- `username`
- `secret_encrypted`
- `secret_hint`
- `version`
- `rotated_by`
- `rotated_at`
- timestamps

Indexes and constraints:

- unique `(tenant_id, extension_id)`
- index `(tenant_id, username)`
- foreign keys to `tenants`, `extensions`, and `users`

## Number Rules

- extension numbers are stored as strings;
- normalization trims whitespace and removes internal spacing;
- allowed length and pattern are configured in `backend/config/extensions.php`;
- leading zeros are preserved;
- the same number may exist in different tenants;
- duplicate active numbers are blocked inside the same tenant.

Default local configuration:

- `EXTENSIONS_MIN_LENGTH=2`
- `EXTENSIONS_MAX_LENGTH=8`
- `EXTENSIONS_PATTERN=^[0-9]+$`

## Credentials

- secrets are generated from a cryptographically secure random source;
- plaintext secrets are returned only once at create or rotate time;
- stored secrets are encrypted at rest;
- ordinary list and detail APIs expose only safe credential metadata;
- serialized models hide the encrypted secret;
- credential rotation increments the stored version and replaces the old secret;
- frontend state clears one-time secrets on modal close and tenant switch.

## Provisioning

Provisioning uses the shared telephony contracts and the fake provider only.

Flow:

`TenantContext`
-> `ExtensionService`
-> `ExtensionProvisioningService`
-> `TelephonyService`
-> `EndpointProvisioningProvider`
-> fake provider

Supported states:

- `pending`
- `provisioning`
- `provisioned`
- `failed`
- `deprovisioning`
- `deprovisioned`

Registration state is simulated and provider-neutral:

- `unknown`
- `unregistered`
- `registered`
- `expired`

The fake provider does not perform SIP signaling or register a real device.

## API

Implemented tenant routes under `/api/v1/extensions`:

- `GET /`
- `GET /assignment-options`
- `POST /`
- `GET /{extension}`
- `PUT /{extension}`
- `PATCH /{extension}`
- `POST /{extension}/rotate-credentials`
- `DELETE /{extension}`

These endpoints use Form Requests, `ExtensionResource`, tenant policies, and tenant permissions.

## Permissions

Implemented tenant permission catalog entries:

- `tenant.extensions.view`
- `tenant.extensions.create`
- `tenant.extensions.update`
- `tenant.extensions.delete`
- `tenant.extensions.manage_credentials`

Platform permissions do not bypass tenant membership or tenant authorization.

## Seed Data

Demo fixtures create deterministic tenant-scoped extension scenarios:

- Tenant A: `2001`, `2002`
- Tenant B: `2001`, `2002`

Testing fixtures create a minimal duplicate-number-across-tenants scenario:

- Tenant A: `2001`
- Tenant B: `2001`

No plaintext secrets are stored in documentation or source-controlled fixtures.

## Relationship to DIDs

Extensions remain separate from DIDs.

- an extension is assigned to a user;
- a DID is assigned to a user;
- the relationship between extension and DID is indirect through the shared user;
- no `extension_id` is stored on `phone_numbers`.

Future routing remains:

- inbound: `DID -> assigned user -> active extension`
- outbound: `user -> primary DID -> caller ID`
