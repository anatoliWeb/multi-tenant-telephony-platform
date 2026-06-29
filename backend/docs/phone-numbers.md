# Phone Numbers and DIDs

## Ownership Model

- `PhoneNumber` belongs to a `Tenant`.
- `PhoneNumber` may belong to an assigned `User`.
- `User` may have many DIDs inside one tenant.
- `User` may have only one primary DID inside one tenant.
- `Extension` does not own a DID and does not store `phone_number_id` or `did_id`.

## Lifecycle

- numbers are normalized through the shared `App\Services\Contacts\PhoneNumberNormalizer`;
- tenant ownership is derived from `TenantContext`;
- uniqueness is enforced by `UNIQUE (tenant_id, normalized_number)`;
- primary DID consistency is protected with transactions, row locks, and a generated unique key for `(tenant_id, assigned_user_id)` when `is_primary = true`.

## API

Implemented tenant routes under `/api/v1/phone-numbers`:

- `GET /`
- `GET /assignment-options`
- `POST /`
- `GET /{phoneNumber}`
- `PUT /{phoneNumber}`
- `PATCH /{phoneNumber}`
- `DELETE /{phoneNumber}`
- `POST /{phoneNumber}/assign`
- `POST /{phoneNumber}/unassign`
- `POST /{phoneNumber}/set-primary`
- `POST /{phoneNumber}/activate`
- `POST /{phoneNumber}/suspend`
- `POST /{phoneNumber}/release`

Additional user-scoped reads:

- `GET /api/v1/users/{user}/phone-numbers`
- `GET /api/v1/users/{user}/primary-did`

## Resolvers

- `UserPrimaryDidResolver` resolves the active tenant's primary DID for a user.
- `InboundDidResolver` resolves `incoming DID -> tenant-owned PhoneNumber -> assigned User`.
- real inbound routing and real outbound caller ID are deferred.

## Deferred Work

- FreeSWITCH DID configuration;
- SIP.js behavior;
- carrier provisioning;
- real inbound routing;
- real outbound calls;
- call-log export;
- billing.

## Call Logs Relationship

Call logs now use DIDs as optional historical references only.

- inbound enrichment resolves `incoming number -> tenant DID -> assigned user`;
- outbound enrichment resolves `user -> primary DID -> caller ID snapshot`;
- call logs keep immutable phone snapshots even when a DID relation exists;
- no direct DID-to-extension ownership relation was added.
