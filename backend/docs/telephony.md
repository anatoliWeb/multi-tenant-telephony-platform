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
- Stage 15.2 adds a local-demo credential gate that only enables registration
  when `APP_ENV=local`, `FREESWITCH_ENABLED=true`, and
  `FREESWITCH_LOCAL_DEMO_CREDENTIALS=true`;
- outside that gate the profile stays metadata-only and the frontend keeps the
  Register action disabled with a clear environment message;
- the softphone service keeps SIP secrets in memory only and clears them on
  tenant switch, logout, modal close, or registration failure;
- Vue Admin is planned to get a support-oriented SIP.js/WebRTC softphone later;
- SIP credentials must remain tenant-scoped and must not leak into logs, browser
  storage, or devtools-friendly global state;
- Stage 15.3 wires the Angular SIP.js softphone to attempt live local-demo
  registration and extension-to-extension calling against a browser-reachable
  transport URL from the SIP profile;
- local development can fall back to `ws://localhost:5066` when the browser
  rejects the local FreeSWITCH WSS certificate chain;
- if the browser does not trust the local FreeSWITCH certificate chain, the
  service surfaces a clear transport error instead of hiding the failure;
- the local demo registration path remains development-only and still depends on
  the local FreeSWITCH provisioning scaffolding rather than SaaS-backed SIP
  credential storage.

## FreeSWITCH Docker Profile

Stage 14 adds an optional local-only FreeSWITCH Docker profile named `freeswitch`.

Current boundary:

- the deterministic fake provider remains the default telephony provider;
- the profile uses `servicebots/freeswitch:latest`, which boots reliably in this environment;
- the stable local container name is `multi-tenant-telephony-platform-freeswitch`, which keeps scripts and docs readable without relying on Compose-generated suffixes;
- the FreeSWITCH profile only prepares container, SIP, WSS, RTP, and Event Socket boundaries;
- the foundation slice avoids bind-mounting `/etc/freeswitch` so the image defaults can boot cleanly;
- Laravel reads the future FreeSWITCH placeholder config from `backend/config/freeswitch.php`;
- browser-facing SIP URIs stay on browser-reachable values such as `localhost`;
- the browser SIP profile resolves `ws://localhost:5066` in local demo mode
  when `FREESWITCH_SIP_WS_URL` is set and otherwise keeps using trusted WSS;
- the FreeSWITCH runtime directory lookup domain can differ inside Docker, so provisioning verification must resolve it separately and must not reuse the browser SIP domain by assumption;
- real SIP credentials must stay out of git and out of browser state;
- no SIP.js, carrier adapter, or live PBX routing is enabled by this foundation slice.
- the local demo provisioning script copies only the demo user XML files needed for the running image and reuses the container's live lookup domain when `FREESWITCH_SIP_DOMAIN` is not set;
- the provisioning script also copies a local `localhost` domain alias and a temporary runtime-domain XML copy so browser-facing SIP auth can resolve `1001@localhost`, `1002@localhost`, `2001@localhost`, and `2002@localhost` under both domains;
- the `localhost` alias and the runtime-domain copy must both contain full auth XML with a password param for each demo user; pointer-only XML is not enough for SIP registration;
- if `user_data <user>@localhost attr password` returns `-ERR no reply` on this image, `find_user_xml id <user> <domain>` is the authoritative check for the resolved auth XML;
- the correct FreeSWITCH user lookup syntax is `user_exists id <user> <domain>`.
- local and testing database resets default `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false` when the flag is not set, so the schema dump loader can keep working against the same Docker MySQL service that backs the telephony demo data.

Stage 15.2 local-demo notes:

- the backend may return a password only for local demo mode;
- the local-demo gate is intentionally development-only and remains disabled in
  every non-local environment;
- the running container exposes its config tree at `/usr/local/freeswitch/conf`,
  including `directory/default/`, which is the provisioning target used by the
  demo script;
- the provisioning foundation should copy or generate only the demo user files
  needed for local testing and should not mount an incomplete `/etc/freeswitch`
  overlay.
- the static XML demo users are a local fallback only and are not the SaaS
  source of truth for SIP credentials.
- the container's current runtime domain has been observed as `172.18.0.12` in
  this environment, but the provisioning script resolves it dynamically from
  `global_getvar local_ip_v4` and generates a temporary runtime-domain XML copy
  so the same workflow keeps working on other Docker networks.
- the future target is DB-backed provisioning behind Laravel, with the current
  static XML files serving only as a local-demo bridge until that integration
  exists.
- the FreeSWITCH test harness adds parseable XML contract tests for directory
  and dialplan output, plus a local smoke script that runs outside the default
  Laravel suite against the live optional container.
- the Stage 15.7 directory endpoint scaffold is local-only, uses an explicit
  configured tenant id, and returns XML or 404 without guessing tenant
  identity from a raw FreeSWITCH request.
- the live smoke script is optional/manual and depends on the local Docker
  runtime, while the Laravel contract tests remain fully deterministic and
  database-backed.

Stage 15.3 browser notes:

- local browser registration uses the browser-reachable SIP WebSocket endpoint
  from the SIP profile, which can be `ws://localhost:5066` for local demo
  fallback or `wss://localhost:7443` for trusted-TLS transport;
- the current FreeSWITCH image advertises both `WS-BIND-URL` and
  `WSS-BIND-URL`, and the optional profile publishes both `5066/tcp` and
  `7443/tcp` for browser access;
- browser trust for the local certificate chain still needs manual
  confirmation when WSS is used;
- 1001 -> 1002 calling is the intended demo pair, but end-to-end browser
  verification remains a manual follow-up in this environment.
- the FreeSWITCH runtime readiness and demo provisioning for `1001` and `1002`
  were verified in Docker against `user_exists id <user> 172.18.0.12`, but
  live browser registration remains partial here because the in-app browser
  control bridge is unavailable in this workspace.

Operational note:

- `mod_xml_curl` may log `Binding has no url` until backend-driven dynamic
  directory and dialplan integration exists;
- that log line is acceptable in the Stage 14 foundation slice.
