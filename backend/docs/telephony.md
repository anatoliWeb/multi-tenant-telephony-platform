# Telephony

## Scope

This slice implements provider-neutral telephony contracts, a deterministic fake provider, tenant-aware application services, and test coverage.

Implemented now:

- shared telephony contracts;
- provider capability model;
- provider-neutral DTOs;
- provider-neutral enums;
- typed telephony exceptions;
- telephony provider registry;
- telephony configuration;
- deterministic fake provider;
- tenant-aware fake provider state;
- idempotency contract for create-style operations;
- contract and fake-provider tests.

Deferred:

- FreeSWITCH adapter;
- real calls;
- SIP.js;
- DIDs, call logs, recordings, queues, billing, and real provider webhooks.
- contacts-to-call integration.

Implemented after this baseline:

- tenant-aware Extensions integrated through the shared endpoint-provisioning contracts;
- fake-provider-backed endpoint lifecycle for extension create, update, suspend, activate, delete, and sync;
- one-time extension credential generation and rotation with encrypted storage.
- tenant-aware call logs and call events integrated through provider-neutral recording services;
- bounded tenant statistics over fake-provider call history;
- fake call lifecycle recording for originate, answer, hold, resume, and hangup flows.
- tenant-aware IVR menus and options with dry-run route resolution, loop detection, timeout actions, and invalid-input actions.

## Contacts Dependency

The tenant-aware Contacts module is now available as a supporting telephony dependency.

Implemented now:

- tenant-scoped caller lookup by normalized phone number;
- shared contact ownership rules for future call-history enrichment;
- import and export foundations for tenant contact data.

Still not implemented:

- dialing from contacts;
- extension resolution from contacts;
- real inbound or outbound call execution;
- any FreeSWITCH or SIP.js runtime behavior.

## Module Boundary

Namespaces:

- `App\Contracts\Telephony`
- `App\DTO\Telephony`
- `App\Enums\Telephony`
- `App\Exceptions\Telephony`
- `App\Services\Telephony`

The telephony domain stays provider-neutral. No shared contract names mention FreeSWITCH.

## Contracts

- `TelephonyProvider`
  - provider identity, capabilities, version, and health.
- `EndpointProvisioningProvider`
  - endpoint lifecycle operations through DTOs.
- `CallControlProvider`
  - call origination and lifecycle operations.
- `ConferenceControlProvider`
  - minimal future-facing conference lifecycle operations.
- `TelephonyHealthProvider`
  - safe provider health reporting.

## Tenant Boundary

- application code resolves tenant identity through `TenantContext`;
- provider input DTOs carry tenant identity explicitly;
- fake provider state is partitioned by tenant id;
- idempotency keys are tenant-scoped;
- no production default-tenant fallback exists in this module.

## Idempotency

Implemented for:

- endpoint creation;
- call origination;
- conference creation.

Rules:

- same tenant plus same operation plus same idempotency key plus same payload returns the same result;
- same key in a different tenant is isolated;
- same key with a different payload raises a typed conflict.

## Fake Provider

The fake provider supports configurable capabilities and deterministic failure injection.

Supported simulated behavior:

- endpoint create, update, suspend, activate, delete, fetch;
- call originate, answer, hold, resume, hang up, transfer, mute;
- conference create, destroy, participant add, participant remove, participant mute, participant list;
- provider health reporting.

It does not simulate SIP signaling, RTP, or media transport.

## Configuration

Config file: `backend/config/telephony.php`

Safe defaults:

- `enabled=false`
- `default_provider=fake`
- no real credentials
- fake provider enabled for local development and tests

Environment example:

- `TELEPHONY_ENABLED=false`
- `TELEPHONY_DEFAULT_PROVIDER=fake`

## Logging

`TelephonyService` emits structured success and failure logs with:

- tenant id;
- provider id;
- operation;
- correlation id;
- duration.

Logs are sanitized through the existing structured log sanitizer and do not include secrets.

## Tests

Coverage includes:

- DTO serialization;
- safe exception serialization;
- registry resolution and disabled behavior;
- fake provider capability restrictions;
- endpoint lifecycle;
- call lifecycle;
- conference lifecycle;
- deterministic failure injection;
- tenant isolation;
- idempotency;
- application-service tenant enforcement.

## Phone Numbers and DIDs

The DID inventory slice is tenant-aware and provider-neutral.

Current rules:

- a DID belongs to a tenant and may be assigned to a user;
- a user may have multiple DIDs per tenant;
- a user may have one primary DID per tenant;
- extensions stay separate and are linked only through the assigned user.

Current implementation keeps DID inventory provider-neutral; the new Angular
softphone foundation lives in a separate call-control slice and does not change
DID routing behavior yet.

## Call Logs Integration

The telephony service now records provider-neutral call history through:

- `CallRecordingService`
- `CallLogService`
- `CallLifecycleService`
- `CallEventService`

Current behavior:

- fake-provider call origination creates a tenant-owned call log and call-created or ringing events;
- answer, hold, resume, and hangup transitions append provider-neutral events and reconcile lifecycle state;
- historical caller and callee snapshots are stored even when related users, extensions, DIDs, or contacts are linked;
- no real SIP transport, RTP, or provider webhooks are involved.

## IVR Integration

The IVR slice is configuration-only and reuses tenant-local telephony data.

Current behavior:

- menus and options are stored per tenant;
- route validation rejects self-loops and obvious nested menu loops;
- timeout and invalid-input actions can repeat, route, or hang up;
- route testing returns a dry-run plan for the active tenant only;
- no audio playback, real DTMF runtime, SIP.js, or FreeSWITCH integration is active yet.

## Softphone Planning

The next call-control slice has started as an Angular foundation, but real SIP
registration remains intentionally disabled until tenant-safe directory
provisioning exists.

Current boundary:

- Angular remains the primary tenant softphone surface;
- the browser softphone now loads a tenant-scoped SIP profile for the selected
  extension and keeps credentials out of persistent browser state;
- the SIP profile endpoint is `GET /api/v1/extensions/{extension}/sip-profile`
  and returns metadata only in the normal environment;
- the profile endpoint is metadata-only in the normal environment, so the UI
  can render call state, microphone checks, and placeholder actions without
  leaking secrets;
- Vue Admin is planned to get a support-oriented SIP.js/WebRTC softphone later;
- SIP credentials must remain tenant-scoped and must not leak into logs, browser
  storage, or devtools-friendly global state;
- this slice adds the Angular SIP.js dependency, but it does not yet wire live
  registration because the FreeSWITCH directory/dialplan provisioning slice is
  still pending.

## FreeSWITCH Docker Profile

Stage 14 adds an optional local-only FreeSWITCH Docker profile named `freeswitch`.

Current boundary:

- the deterministic fake provider remains the default telephony provider;
- the profile uses `servicebots/freeswitch:latest`, which boots reliably in this environment;
- the FreeSWITCH profile only prepares container, SIP, WSS, RTP, and Event Socket boundaries;
- the foundation slice avoids bind-mounting `/etc/freeswitch` so the image defaults can boot cleanly;
- Laravel reads the future FreeSWITCH placeholder config from `backend/config/freeswitch.php`;
- real SIP credentials must stay out of git and out of browser state;
- no SIP.js, carrier adapter, or live PBX routing is enabled by this foundation slice.

Operational note:

- `mod_xml_curl` may log `Binding has no url` until backend-driven dynamic
  directory and dialplan integration exists;
- that log line is acceptable in the Stage 14 foundation slice.
