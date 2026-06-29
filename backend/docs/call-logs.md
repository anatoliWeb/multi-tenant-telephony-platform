# Call Logs and Statistics

## Scope

The call-history slice is now implemented as a tenant-aware, provider-neutral audit trail for simulated telephony activity.

Implemented now:

- tenant-owned `CallLog` records;
- append-only tenant-owned `CallEvent` records;
- immutable caller and callee number snapshots;
- optional historical links to users, extensions, DIDs, and contacts;
- provider call and provider event idempotency;
- lifecycle reconciliation for created, ringing, answered, held, resumed, completed, failed, and cancelled flows;
- bounded tenant statistics;
- tenant-scoped APIs, policies, resources, demo data, CSV export, and Angular UI.

Deferred:

- real provider webhook ingestion;
- real SIP calls;
- FreeSWITCH event ingestion;
- recordings;
- billing, balances, invoices, and monetary rating.

## Ownership Model

- every `CallLog` belongs to exactly one tenant;
- every `CallEvent` belongs to the same tenant as its parent call log;
- tenant ownership is derived from `TenantContext`;
- client input cannot override `tenant_id`;
- route binding fails closed outside the active tenant.

## Historical Snapshots

Call logs keep immutable snapshots for:

- `from_number`
- `from_normalized_number`
- `to_number`
- `to_normalized_number`

Optional relations may point to:

- `caller_user_id` and `callee_user_id`
- `caller_extension_id` and `callee_extension_id`
- `caller_phone_number_id` and `callee_phone_number_id`
- `caller_contact_id` and `callee_contact_id`

These relations are nullable historical references only. Deleting or renaming a related record must not make old call history unreadable.

## Lifecycle

Provider-neutral directions:

- `inbound`
- `outbound`
- `internal`

Provider-neutral statuses:

- `created`
- `initiated`
- `ringing`
- `answered`
- `held`
- `completed`
- `failed`
- `cancelled`

Final dispositions:

- `answered`
- `no_answer`
- `busy`
- `rejected`
- `cancelled`
- `failed`
- `unknown`

## Events and Idempotency

`CallEvent` is append-only through normal application behavior.

Idempotency rules:

- `(tenant_id, provider_id, provider_call_id)` is unique for call creation;
- `(tenant_id, provider_id, provider_event_id)` is unique for event ingestion;
- exact duplicate events are ignored safely;
- conflicting duplicate event identifiers fail with a typed conflict;
- the same provider identifiers may be reused in another tenant without leakage.

Provider payloads are sanitized before persistence and are capped to safe bounded JSON structures.

## Duration Rules

- `total_seconds = ended_at - started_at`
- `ringing_seconds = answered_at - ringing_at`
- `talk_seconds = ended_at - answered_at`
- `billable_seconds` currently follows `talk_seconds`

Safety rules:

- durations never go negative;
- unanswered calls keep `talk_seconds = 0`;
- internal calls are marked `non_billable`;
- external calls remain billing-ready but unrated;
- no money is calculated in this slice.

## Resolution

Tenant-scoped enrichment uses:

- `InboundDidResolver` for inbound DID ownership;
- `UserPrimaryDidResolver` through the telephony flow for outbound caller ID snapshots;
- `ContactQueryService` for contact enrichment;
- active extension lookup for user-to-extension resolution.

No lookup is allowed to search tenant-owned resources globally.

## API

Tenant routes under `/api/v1/call-logs`:

- `GET /`
- `GET /statistics`
- `GET /filter-options`
- `GET /export`
- `GET /{callLog}`
- `GET /{callLog}/events`

Implemented permissions:

- `call_logs.view`
- `call_logs.view_own`
- `call_logs.view_all`
- `call_logs.export`
- `call_logs.view_statistics`

Current visibility rules:

- users with `call_logs.view_own` only see calls where they are the caller or callee user;
- users with `call_logs.view_all` may see tenant call history;
- statistics follow the same scope boundary;
- platform permissions alone do not bypass tenant APIs.

## Statistics

Implemented metrics:

- `total_calls`
- `answered_calls`
- `missed_calls`
- `failed_calls`
- `inbound_calls`
- `outbound_calls`
- `internal_calls`
- `total_talk_seconds`
- `average_talk_seconds`
- `answer_rate`

Grouped summaries:

- `calls_by_day`
- `calls_by_status`
- `calls_by_direction`
- `top_users`

The default window is 30 days and the maximum allowed range is capped at 92 days.

## Current Boundary

Call logs and statistics are implemented.

Call data is currently produced by tests, seeders, and fake-provider flows.

No real SIP calls occur.

No monetary billing occurs.
