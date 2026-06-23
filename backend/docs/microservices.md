# Microservices Preparation (Future)

Current strategy remains a modular monolith. This document is a planning artifact for future extraction decisions only.

## Extractable Domains

### Notifications

- Domain name: Notifications
- Extraction priority: Medium
- Business/technical reason: high async volume, delivery channels, independent scaling potential.
- Current module owner: Notifications module (`NotificationService`, `NotificationPreferenceService`, notification jobs/events).
- Current dependencies: Auth/Identity, Realtime, Activity, Users/RBAC.
- Current data ownership: notifications and notification preferences.
- Required API/async contracts: stable notification API, event schema, delivery status/retry contract.
- Migration complexity: Medium
- Operational complexity: Medium
- Extraction blockers: preference coupling with users, realtime coupling, delivery idempotency hardening.
- Readiness level: L3
- Recommended decision: Candidate later.

### Realtime/WebSocket

- Domain name: Realtime/WebSocket
- Extraction priority: Medium
- Business/technical reason: traffic profile and scaling concerns differ from core request/response API.
- Current module owner: Realtime module (`routes/channels.php`, broadcast events/jobs, `RealtimeLogService`).
- Current dependencies: Auth/Identity, Chat, Notifications, Activity.
- Current data ownership: presence/protocol-level signaling state.
- Required API/async contracts: channel authorization contract, safe presence payload contract, broadcast event versioning.
- Migration complexity: Medium/High
- Operational complexity: High
- Extraction blockers: auth coupling, channel authorization consistency, incident/debug complexity.
- Readiness level: L2/L3
- Recommended decision: Candidate later.

### External Webhooks

- Domain name: External Webhooks
- Extraction priority: Medium/High
- Business/technical reason: integration-specific reliability concerns (retry, replay, signature validation).
- Current module owner: Webhooks/External API module (`ChatWebhookDeliveryService`, `ChatWebhookSigningService`, replay/signature services).
- Current dependencies: Chat domain events, queue subsystem, security policies.
- Current data ownership: webhook endpoints, delivery history, callback metadata.
- Required API/async contracts: callback signature contract, idempotency contract, delivery status/retry contract.
- Migration complexity: Medium
- Operational complexity: Medium/High
- Extraction blockers: token scope lifecycle coupling, callback reliability guarantees, ordering expectations.
- Readiness level: L3
- Recommended decision: Candidate later.

### Activity/Audit

- Domain name: Activity/Audit
- Extraction priority: Medium
- Business/technical reason: naturally event-driven append-only domain.
- Current module owner: Activity module (`ActivityService`, activity listeners/events).
- Current dependencies: Auth, Users/RBAC, Chat, Notifications.
- Current data ownership: activity log records and safe audit metadata.
- Required API/async contracts: canonical activity event schema, retention/archive policy contract.
- Migration complexity: Low/Medium
- Operational complexity: Medium
- Extraction blockers: event ordering/duplication guarantees, schema consistency across emitters.
- Readiness level: L3
- Recommended decision: Candidate later.

### Auth/Identity

- Domain name: Auth/Identity
- Extraction priority: Low/Medium
- Business/technical reason: long-term identity centralization is possible in larger platform setups.
- Current module owner: Auth module (`/api/v1/auth/*`, session/token lifecycle, Sanctum flows).
- Current dependencies: Users/RBAC, Monitoring, Security policies.
- Current data ownership: sessions, personal access tokens, identity context.
- Required API/async contracts: stable identity API, token/session validation contract, revocation semantics.
- Migration complexity: High
- Operational complexity: High
- Extraction blockers: highest blast radius, latency/security risk, broad cross-module dependency.
- Readiness level: L2
- Recommended decision: Not now.

### Chat

- Domain name: Chat
- Extraction priority: Low now, potentially High later
- Business/technical reason: high-volume and complex aggregate boundaries (messages, participants, read/delivery, realtime, webhooks).
- Current module owner: Chat module (`ChatConversationService`, `ChatMessageService`, `ChatReadStateService`, chat webhook/realtime services).
- Current dependencies: Users/RBAC, Notifications, Realtime, Activity, External Webhooks.
- Current data ownership: conversations, messages, participants, attachments, read state, delivery state.
- Required API/async contracts: strict conversation/message contract, participant authorization contract, webhook/realtime payload versioning.
- Migration complexity: High
- Operational complexity: High
- Extraction blockers: data consistency, permission coupling, race conditions under load, migration risk.
- Readiness level: L2/L3
- Recommended decision: Not now, reassess later with load and team readiness.

### API Docs/Monitoring

- Domain name: API Docs/Monitoring
- Extraction priority: Low
- Business/technical reason: tooling-like modules can be separated later if platform scope expands.
- Current module owner: API Docs + Monitoring modules (`ApiDocsPermissionService`, `ApiDocsOpenApiFilterService`, `MonitoringHealthService`).
- Current dependencies: RBAC and app runtime checks.
- Current data ownership: policy/config and health summaries.
- Required API/async contracts: stable docs access/filter contract, health endpoint contract.
- Migration complexity: Low
- Operational complexity: Medium
- Extraction blockers: low product value vs added operational overhead.
- Readiness level: L2
- Recommended decision: Not now.

## Extraction Decision Matrix

| Domain | Readiness Level | Priority | Coupling | Data Ownership Complexity | Async Need | Operational Risk | Recommendation |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Notifications | L3 | Medium | Moderate | Medium | High | Medium | Candidate later |
| Realtime/WebSocket | L2/L3 | Medium | High | Medium | High | High | Candidate later |
| External Webhooks | L3 | Medium/High | Moderate | Medium | High | Medium/High | Candidate later |
| Activity/Audit | L3 | Medium | Moderate | Low/Medium | High | Medium | Candidate later |
| Auth/Identity | L2 | Low/Medium | High | High | Medium | High | Not now |
| Chat | L2/L3 | Low now / High later | High | High | High | High | Not now, reassess later |
| API Docs/Monitoring | L2 | Low | Low/Moderate | Low | Low | Medium | Not now |

## Extraction Anti-Patterns

Do not extract if one or more of the following are true:

- module boundaries are not stable;
- data ownership is unclear;
- cross-service DB writes would be needed;
- distributed transactions would be required for core flows;
- observability and operational runbooks are not ready;
- the team cannot operate additional service lifecycle safely;
- latency/security impact is not measured and understood.

Explicitly avoid:

- shared database microservices;
- direct cross-service DB writes;
- distributed transactions as the default integration strategy.

## Current Position

- Modular monolith remains the current architecture strategy.
- No microservice extraction is performed in this phase.
- No Kafka/RabbitMQ adoption is introduced in this phase.

## API Gateway Strategy

No real gateway is added in this phase. This section defines future gateway strategy only.

### Current State

- Laravel API-first modular monolith with `/api/v1/*` contracts.
- Authentication uses Sanctum/session/bearer flows.
- OpenAPI is generated from monolith routes/controllers.
- Permission-aware API docs access is enforced.
- Rate limiting and security headers are currently enforced inside the monolith.

### Future Gateway Responsibilities

- Route public API requests to target services by stable prefixes.
- Central TLS termination and edge security policy enforcement.
- Auth/session/token forwarding strategy for trusted internal services.
- Coarse gateway-level rate limiting for high-risk entrypoints.
- Request correlation propagation (`request_id`/`correlation_id`).
- Versioning boundary enforcement (for example `/api/v1` -> `/api/v2` migration path).
- OpenAPI aggregation across services for external API consumers.
- WebSocket/realtime routing via reverse proxy strategy.
- Monitoring/logging integration for request lifecycle and edge failures.
- Canary/blue-green rollout readiness at edge layer.

### What Gateway Should NOT Own

- Business/domain logic.
- Direct database access.
- Fine-grained domain authorization decisions owned by services.
- Hardcoded secrets in route configuration.
- Raw request/response payload logging.

### Gateway Routing Model (Future)

| API Group | Current Route Prefix | Future Service | Gateway Responsibility | Notes |
| --- | --- | --- | --- | --- |
| Auth / Identity | `/api/v1/auth/*` | Identity Service | Route + coarse auth edge checks + token/session forwarding | Service remains source of truth for auth lifecycle |
| Users / RBAC | `/api/v1/users*`, `/api/v1/roles*`, `/api/v1/permissions*` | Access Service | Route + correlation + coarse rate limits | Fine permissions stay in service |
| Dashboard / Stats | `/api/v1/stats`, `/api/v1/meta*` | Platform API Service | Route + response timeout policy | Keep lightweight sync reads |
| Notifications | `/api/v1/notifications*` | Notification Service | Route + burst smoothing limits | Event-driven fan-out remains internal |
| Chat | `/api/v1/chat/*` | Chat Service | Route + upload/chat edge limits | Internal chat permissions remain service-owned |
| Webhooks / External API | `/api/v1/chat/external/*`, `/api/v1/chat/webhook-endpoints*` | Integration Service | Route + external hardening + coarse limits | Signature/scope verification remains service-owned |
| Monitoring | `/api/v1/system/health`, `/health` | Platform/Monitoring Service | Route + allowlist policy + liveness/readiness split | Keep public liveness minimal |
| API Docs | `/docs/api*` | Docs Service (or platform edge) | Route + access policy + filtering integration | Raw internal specs stay restricted |

### Auth Forwarding Strategy

- Current: Sanctum/session/bearer is validated inside Laravel monolith.
- Future gateway approach:
  - perform coarse edge checks when applicable;
  - forward identity claims and correlation context to trusted internal services;
  - preserve `Authorization` only for trusted internal hops;
  - avoid duplicating business permission logic at gateway.
- Service layer remains responsible for domain permissions and access control.

### Rate Limiting Strategy

- Gateway-level coarse limits (edge abuse protection):
  - auth login endpoints,
  - external API entrypoints,
  - docs access routes,
  - heavy upload entrypoints.
- Service-level fine-grained limits (domain behavior protection):
  - chat message send,
  - chat typing,
  - webhook management actions.
- Avoid confusing double-blocking; align policies and document expected 429 behavior.
- Preserve compatible 429 envelope style where feasible.

### OpenAPI Strategy

- Current: single Scramble-generated spec from modular monolith.
- Future:
  - per-service OpenAPI specs;
  - gateway-level aggregated external spec;
  - permission-aware docs filtering remains identity/permission-based;
  - raw internal specs remain restricted by access policy.

### WebSocket / Reverb Strategy

- Current: Reverb/channels are hosted and authorized in monolith.
- Future:
  - gateway/reverse proxy routes websocket traffic to realtime service;
  - auth remains validated by realtime/identity service contracts;
  - no public sensitive channels;
  - propagate request/session correlation metadata where possible.

### Gateway Anti-Patterns

- No business logic in gateway.
- No shared-database coordination through gateway.
- No gateway direct DB access.
- No raw payload logging at gateway.
- No exposure of private internal service URLs publicly.
- No service-to-service calls routed through public gateway unless intentionally designed.

## Async Communication Strategy

No Kafka/RabbitMQ is added in this phase. This section defines future async strategy only.

### Current State

- Laravel domain events/listeners are used for module decoupling.
- Redis-backed queues process async workloads.
- Webhook delivery uses queued jobs with retries/backoff.
- Queue priorities are already defined for critical flows.
- Safe queue/realtime logging foundation is in place.
- Idempotency foundations already exist for external messages and webhook callbacks.

### Future Async Responsibilities

- Decouple service boundaries through explicit async contracts.
- Isolate side effects from synchronous request paths.
- Preserve retries/backoff and failure visibility.
- Preserve idempotency under at-least-once delivery.
- Carry `request_id`/`correlation_id` across async hops.
- Avoid synchronous chains for non-critical workflows.

### Message Contract Rules

Event/message envelope target:

```json
{
  "event_id": "uuid",
  "event_type": "module.entity.action",
  "event_version": 1,
  "occurred_at": "2026-05-30T12:00:00Z",
  "producer": "chat",
  "correlation_id": "req-...",
  "idempotency_key": "idem-...",
  "payload": {}
}
```

Rules:

- Prefer IDs over full model snapshots.
- Keep event contracts versioned (`event_version`).
- Keep payloads backward-compatible.
- Consumers must ignore unknown fields safely.
- Producers must not depend on consumer internals.
- Never include secrets/tokens/signatures/raw payloads.

### Delivery Guarantees

- Default guarantee: at-least-once delivery.
- Consumers must be idempotent.
- Retries with bounded backoff are required.
- Failed jobs/dead-letter strategy must remain observable.
- Poison message handling must quarantine and alert, not loop forever.
- Replay strategy must be explicit and auditable.
- Ordering is not guaranteed unless contract explicitly says otherwise.

### Outbox/Inbox Strategy (Future)

- Use Outbox pattern for reliable domain event publishing.
- Write domain state + outbox record in the same DB transaction.
- Publisher worker drains outbox and emits transport messages.
- Use Inbox/dedup storage for idempotent consumer handling.
- This is a future implementation strategy, not implemented now.

### Queue/Topic Model (Future)

Future logical async streams:

- `notifications.events`
- `chat.events`
- `webhook.delivery`
- `activity.audit`
- `realtime.broadcast`
- `identity.events`

Current Laravel queue mapping baseline:

- `webhooks`
- `realtime`
- `notifications`
- `activity`
- `emails`
- `default`
- `low`

### Async Anti-Patterns

- No distributed transactions as default integration path.
- No synchronous service chains for side effects.
- No raw DB polling across services as event substitute.
- No shared mutable event payload contracts.
- No secrets/tokens in async messages.
- No fire-and-forget without observability and failure tracking.
- No consumer-specific payload shaping inside producer events.

## Auth Service Strategy

No standalone Auth Service is added in this phase. This section defines future extraction strategy only.

### Current State

- Auth/Identity currently lives inside the Laravel modular monolith.
- Sanctum/session authentication is primary for admin web flow.
- Bearer token flow is supported as API fallback.
- Personal access tokens are managed in current monolith boundaries.
- RBAC is integrated with users/roles/permissions and enforced in-domain.
- API docs access (`api.docs.view`, `api.docs.view.full`) is permission-based.
- External chat tokens are separate from user auth identity flow.

### Future Auth Service Responsibilities

- User identity lifecycle and identity claims.
- Authentication (session/token issuance and validation).
- Token issuing/revocation and expiration policy.
- Session validation strategy for first-party clients.
- Service-to-service auth strategy (future, if required).
- Password reset and MFA readiness path.
- Security/audit events for identity-sensitive operations.

### What Auth Service Should NOT Own

- Business-domain permission rules without stable boundary contracts.
- Chat conversation/participant access decisions.
- Notification delivery policies.
- Direct database access to other service-owned data.
- Frontend-specific UI behavior decisions.

### Identity and RBAC Boundary Options

#### Option A: Auth + RBAC together

Pros:

- Single source of truth for identity and permissions.
- Simpler gateway auth forwarding and fewer cross-service lookups.

Cons:

- Higher coupling and broader blast radius.
- RBAC change cadence directly impacts auth service stability.

#### Option B: Auth identity only, RBAC remains platform/domain service

Pros:

- Smaller auth service surface and clearer ownership.
- Domain permissions remain close to domain logic.

Cons:

- Requires permission claim sync/query contract strategy.
- More coordination for cross-service authorization checks.

Recommended project path:

- Not extracting now.
- Keep Auth/RBAC inside modular monolith until boundaries and operational need are clear.
- Preferred future sequence: extract Auth/Identity boundary first; consider RBAC extraction later after permission contracts stabilize.

### Token/Session Strategy (Future)

- Current baseline remains Sanctum/session with bearer fallback.
- Future gateway forwards trusted identity claims and correlation context.
- Internal services must never trust raw frontend claims blindly.
- Prefer short-lived access tokens when auth is externalized.
- Service-to-service tokens (or mTLS) are future options.
- Token revocation/expiration remains mandatory.
- No plain token storage.
- No token logging (`Authorization`, cookies, token bodies).

### Phased Migration Strategy

Phase 0: modular monolith current state

- Gate: current auth/session/token flows stable.
- Risk: premature extraction with unstable contracts.

Phase 1: document stable auth API contracts

- Gate: contract tests cover login/me/logout/session/token variants.
- Risk: hidden coupling with RBAC/meta/bootstrap responses.

Phase 2: separate auth boundary internally

- Gate: internal service boundary clearly defined and tested.
- Risk: duplicated validation/permission logic during transition.

Phase 3: introduce gateway identity forwarding

- Gate: signed/trusted claim propagation and correlation IDs validated.
- Risk: inconsistent trust model across services.

Phase 4: externalize token/session validation

- Gate: revocation, expiration, and fallback behavior proven.
- Risk: auth outage blast radius and latency regressions.

Phase 5: optional standalone Auth Service

- Gate: observability/runbooks/on-call readiness and rollback path.
- Risk: operational overhead exceeds product benefit if adopted too early.

### Auth Strategy Anti-Patterns

- No duplicated auth logic across services.
- No shared auth DB access from every service.
- No long-lived unscoped tokens by default.
- No gateway-only authorization for domain-level rules.
- No unsigned/unvalidated token or claim trust.
- No service trusting frontend-provided roles blindly.
- No logging of `Authorization` headers, tokens, or cookies.

## Notification Service Extraction Strategy

No standalone Notification Service is extracted in this phase. This section defines future extraction strategy only.
Notification service is not extracted in this phase.

### Current State

- Notifications currently live inside Laravel modular monolith.
- Notification endpoints are part of `/api/v1/notifications*`.
- Realtime/user notification channels exist.
- Unread count endpoints and mark-read flows exist.
- Permission checks protect notification endpoints.
- Notification events/jobs integrate with realtime and activity pipelines.

### Future Notification Service Responsibilities

- Notification inbox lifecycle.
- Unread counters and read-state consistency.
- Delivery status tracking.
- User notification preferences.
- Notification templates (future scope).
- Channel routing: in-app, email, push, webhook (future scope).
- Retention/cleanup policies.
- Notification analytics (optional future scope).

### What Notification Service Should NOT Own

- Core business logic deciding that domain events happened.
- Chat message/conversation state creation.
- Auth/user ownership source of truth.
- RBAC source of truth.
- Direct writes to other service-owned databases.
- Raw frontend UI state decisions.

### Data Ownership Model (Future)

Notification service owns:

- notifications
- notification deliveries
- read/unread state
- notification preferences
- templates (if introduced)

Other services emit domain events, for example:

- `chat.message.created`
- `webhook.delivery.failed`
- `user.created`
- `system.alert.created`

Notification service consumes events and creates notification records.

### Contracts Required Before Extraction

#### API contracts

- `GET /notifications`
- `GET /notifications/unread-count`
- `PATCH /notifications/{id}/read`
- `PATCH /notifications/read-all`
- preferences endpoints (future-compatible contract)

#### Event contracts

- `notification.requested`
- `notification.created`
- `notification.read`
- `notification.delivery.failed`
- `notification.preference.updated`

#### Payload rules

- IDs over model snapshots.
- No secrets/tokens/raw payloads.
- Versioned event payloads.
- `idempotency_key` required for event consumption.
- `correlation_id` propagation required.

### Async Communication Model

Current:

- Laravel events/listeners/jobs with Redis queues.

Future:

- Producer services emit domain events.
- Notification service consumes event stream.
- Default delivery semantics: at-least-once.
- Consumers are idempotent.
- Outbox/inbox strategy required before extraction.
- Retry/backoff policies for external channels.
- Failed/dead-letter handling and replay visibility.

### Migration Strategy

Phase 0: current modular monolith

- Gate: current notification APIs/realtime behavior stable.
- Risk: premature split breaks unread and realtime consistency.

Phase 1: stabilize notification contracts/tests

- Gate: contract tests for inbox/unread/read-all/preferences.
- Risk: hidden coupling in event payload assumptions.

Phase 2: introduce internal notification facade/contract (if needed)

- Gate: explicit service boundary and ownership docs.
- Risk: temporary dual-path complexity.

Phase 3: event-contract-only notification creation

- Gate: producer domains emit stable events only.
- Risk: duplicate notifications without strict idempotency.

Phase 4: externalize notification storage/read APIs

- Gate: data migration and rollback runbook validated.
- Risk: read latency and unread drift under load.

Phase 5: optional standalone Notification Service

- Gate: observability, on-call readiness, SLOs, and rollback path.
- Risk: ops overhead exceeds product benefit if traffic profile is still moderate.

### Risks and Blockers

Risks:

- unread count consistency drift
- realtime coupling and fan-out latency
- user preferences coupling
- duplicate notifications
- ordering assumptions in consumers
- retention policy and storage growth
- cross-service authorization assumptions

Blockers before extraction:

- unstable event envelope
- no outbox/inbox strategy
- limited observability/metrics
- missing idempotency guarantees
- unclear ownership boundaries
- no migration/rollback playbook

### Notification Extraction Anti-Patterns

- No service directly writes notification DB except the owner.
- No synchronous blocking call from Chat to Notification for non-critical UX paths.
- No notification service deciding core domain state transitions.
- No raw payloads or secrets in notification events.
- No per-consumer custom event payloads from producers.
- No extraction before observability and replay controls are ready.

## Realtime Service Extraction Strategy

No standalone Realtime Service is extracted in this phase. This section defines future extraction strategy only.
Realtime service is not extracted in this phase.

### Current State

- Realtime currently lives inside Laravel modular monolith.
- Laravel Reverb/broadcasting is used for websocket transport.
- Channel authorization is handled in `routes/channels.php` with domain service checks.
- Private/presence channels are permission-aware and participant-aware.
- Safe realtime/presence payload tests exist.
- Vue Admin uses realtime client diagnostics (WS/EV/ON/PG).

### Future Realtime Service Responsibilities

- WebSocket connection/session handling.
- Presence state lifecycle.
- Channel subscription authorization handshake.
- Event fan-out to connected clients.
- Connection metrics and delivery diagnostics.
- Reconnect/backoff observability.
- Optional multi-node scaling.

### What Realtime Service Should NOT Own

- Business-domain decision making.
- Chat message creation or mutation.
- Notification creation logic.
- RBAC source of truth.
- Direct writes to domain-owned databases.
- Raw token/session ownership source.
- Frontend UI state ownership.

### Auth and Channel Authorization Model

Current:

- Laravel session/Sanctum auth integration.
- `channels.php` authorization callbacks.
- Domain checks through chat access/presence/permission services.

Future:

- Gateway/realtime runtime validates connection identity context.
- Realtime service validates signed claims directly or via Identity/Auth contract.
- Domain services remain source of truth for channel access decisions.
- Channel auth decisions may be cached briefly, with invalidation on permission/participant changes.
- Sensitive channels are never public.
- Presence payload remains safe/minimal.

Required behaviors:

- identity propagation with correlation context
- permission and participant authorization checks
- channel revocation/invalidation strategy
- token/session expiration handling
- reconnect behavior with safe re-auth

### Event Input Model

Candidate inputs:

- A) Internal event stream from core services
- B) Queue/topic (for example `realtime.broadcast`)
- C) Secured internal push/webhook style bridge (if required)
- D) Direct DB polling (explicitly discouraged)

Recommended future path:

- Async event stream/topic as primary input.
- Versioned event envelope.
- Idempotent event handling.
- Safe payload-only fan-out.
- `correlation_id` propagation end-to-end.

### Data Ownership Model

Realtime service owns:

- connection/session state
- presence state
- subscription state
- delivery diagnostics and connection metrics

Realtime service does not own:

- chat messages
- notifications
- users/RBAC records
- core business aggregates
- webhook delivery records

### Migration Strategy

Phase 0: current Laravel Reverb inside monolith

- Gate: current channels and payload contracts stable.
- Risk: introducing external runtime too early causes auth drift.

Phase 1: stabilize channel contracts and payload tests

- Gate: private/presence auth and safe payload tests are comprehensive.
- Risk: hidden payload or auth coupling.

Phase 2: centralize realtime event envelope

- Gate: shared envelope contract versioned and documented.
- Risk: consumer mismatch across event producers.

Phase 3: introduce event stream/topic for realtime fan-out

- Gate: idempotent fan-out with replay/error visibility.
- Risk: duplicates/order assumptions under retry.

Phase 4: gateway/proxy routes WS traffic to dedicated realtime runtime

- Gate: identity forwarding + channel auth validation proven.
- Risk: reconnect storms and stale auth state.

Phase 5: optional standalone Realtime Service

- Gate: observability, load tests, runbooks, rollback path.
- Risk: operational overhead outweighs benefit if traffic profile is modest.

### Risks and Blockers

Risks:

- channel authorization drift
- presence consistency issues
- reconnect storms
- event ordering assumptions
- duplicate event fan-out
- stale subscriptions after permission changes
- multi-node fan-out complexity
- observability gaps
- frontend compatibility regressions

Blockers before extraction:

- unstable event envelope
- missing identity forwarding strategy
- missing channel invalidation strategy
- missing connection/event latency metrics
- no realistic load testing baseline
- undocumented fallback/reconnect behavior

### Realtime Extraction Anti-Patterns

- No business logic in realtime runtime.
- No direct writes to Chat/Notification tables.
- No raw broadcast payloads with secrets/tokens.
- No public sensitive channels.
- No trust in frontend-provided channel permissions.
- No direct DB polling across services as fan-out transport.
- No extraction before observability and load testing readiness.

## Kafka / RabbitMQ Evaluation

### Current Recommendation

- Do not add Kafka/RabbitMQ now.
- Keep current Laravel Redis queues for modular monolith workloads.
- Re-evaluate broker choice only when measurable scale/operational needs appear.

### Broker Comparison Matrix

| Option | Best for | Strengths | Weaknesses | Operational complexity | Fit for current project | Future use case |
| --- | --- | --- | --- | --- | --- | --- |
| Redis Queues | Background jobs in monolith | Simple Laravel integration, low ops overhead, fast adoption | Limited replay/log semantics vs streaming platforms | Low | Strong fit now | Continue as baseline |
| Redis Streams | Lightweight stream processing | Consumer groups, replay window, closer to stream model than basic queues | More design complexity than queues, still limited ecosystem vs Kafka | Low/Medium | Conditional fit | Step-up option before Kafka |
| RabbitMQ | Work queues + routing topology | Exchanges/routing keys/ack model, flexible fan-out patterns | Higher ops complexity, broker tuning/visibility requirements | Medium | Not needed now | Candidate for multi-service routing complexity |
| Kafka | High-throughput event log | Strong replay/retention semantics, many independent consumers, analytics/event sourcing fit | Highest ops burden, platform complexity, steeper learning curve | High | Not fit now | Candidate for large event-stream ecosystems |
| Cloud Queue (SQS-like) | Managed queueing | Managed operations, durability, simpler infra ownership | Vendor coupling, routing/event-log limits vs Kafka/RabbitMQ | Medium | Not needed now | Optional managed path if infra strategy changes |

### Decision Criteria

Use Redis queues when:

- modular monolith architecture is still primary;
- async needs are job-oriented and retry/backoff is sufficient;
- long-lived replay/event-log semantics are not required;
- team prefers low operational overhead.

Consider RabbitMQ when:

- cross-service routing topology becomes complex;
- acknowledgements/routing keys/exchanges are required;
- throughput is moderate but reliability topology needs are high;
- team can operate broker lifecycle and observability.

Consider Kafka when:

- high-throughput event streaming is required;
- long retention/replay is required;
- multiple independent consumers need the same event log;
- analytics/event-sourcing style workloads are justified;
- team can run Kafka operations safely.

Consider Redis Streams when:

- lightweight stream semantics are needed;
- a middle path between Redis queues and Kafka is preferred;
- operational expansion must remain limited.

### Domain-Specific Recommendation

- Notifications: Redis queues now; RabbitMQ later if channel routing topology grows significantly.
- Realtime: Reverb/Redis now; Kafka not needed for low-latency fan-out unless unified stream integration grows.
- External Webhooks: Redis queues now; RabbitMQ later if retry/routing workflows become complex.
- Activity/Audit: Redis now; Kafka later if replay-heavy analytics/event-log requirements emerge.
- Chat: Redis queues now with strict idempotency; Kafka only when event-stream scale and replay needs are proven.
- Auth/Identity: Keep auth async side effects on Redis queues now; broker escalation only with measured cross-service auth event load.

### Migration Path (Future)

Phase 0: Redis queues inside monolith

- Gate: stable queue jobs, retries/backoff, failed-job handling, monitoring.

Phase 1: standardize event envelope

- Gate: versioned contracts with correlation and idempotency fields.

Phase 2: outbox/inbox pattern

- Gate: reliable publish + idempotent consume boundaries.

Phase 3: broker abstraction/publisher interface

- Gate: transport-agnostic producer/consumer contracts.

Phase 4: pilot one domain on selected broker (RabbitMQ/Redis Streams/Kafka)

- Gate: domain pilot metrics, rollback path, no contract regressions.

Phase 5: production broker adoption only after measured need

- Gate: observability, operational ownership, SLO impact, and incident readiness.

### Broker Evaluation Anti-Patterns

- No Kafka just because “microservices”.
- No broker adoption before stable event contracts.
- No broker adoption without observability and tracing.
- No broker adoption without clear operational ownership.
- No broker as replacement for transaction boundaries.
- No secrets/raw payloads in messages.
- No consumer-specific payload contracts.
- No distributed transactions as default integration model.

## Observability Strategy

No heavy observability stack is added in this phase. This section defines future observability strategy only.

### Current State

- Structured Laravel logs are already in place.
- Request correlation is supported through request ID propagation (`request_id` / `X-Request-Id`).
- Queue lifecycle logs and realtime auth/failure logs are already implemented with safe context.
- Public liveness (`/health`) and protected readiness (`/api/v1/system/health`) endpoints exist.
- Container log strategy is documented around stdout/stderr flow.
- No Prometheus/Grafana/ELK/OpenTelemetry runtime is introduced now.

### Future Observability Goals

- Service-level dashboards per extracted domain.
- API error-rate and latency monitoring.
- Queue depth/lag and worker saturation visibility.
- Webhook delivery success/failure and retry visibility.
- Realtime connection counts and event latency tracking.
- Auth/security event visibility across service boundaries.
- API gateway edge metrics and request tracing context.
- Distributed tracing readiness for split services.

### Logs / Metrics / Traces Model

#### Logs

- Keep structured JSON-compatible logs across all services.
- Propagate `request_id` / `correlation_id` in sync and async flows.
- Keep category/module/status fields as baseline dimensions.
- Keep container-first log output (stdout/stderr).
- Never log tokens, secrets, signatures, raw payloads, or response bodies.

#### Metrics

Future candidate metrics:

- `http_request_duration_ms`
- `http_requests_total`
- `api_errors_total`
- `queue_depth`
- `queue_job_duration_ms`
- `webhook_delivery_success_total`
- `webhook_delivery_failed_total`
- `realtime_connections`
- `realtime_auth_denied_total`
- `cache_hit_ratio`
- `db_query_duration_ms`

#### Traces

- Introduce OpenTelemetry only when service split starts.
- Propagate `trace_id` / `span_id` with `correlation_id`.
- Correlate gateway -> service -> queue job -> webhook/realtime side effects.
- Not implemented now.

### SLI/SLO Strategy (Future Candidates)

#### API

- p95 latency
- 5xx error rate
- auth failure rate

#### Queue

- job latency
- failed jobs rate
- retry count
- queue depth

#### Webhooks

- delivery success rate
- retry rate
- dead-letter/failed count

#### Realtime

- connection count
- auth denied rate
- event delivery latency
- reconnect storm detection

#### Database/Cache

- slow query count/duration
- cache hit ratio
- Redis availability

### Alerting Strategy (Future)

- API 5xx spike alert.
- Queue backlog/lag alert.
- Failed webhook spike alert.
- Redis unavailable alert.
- DB unavailable alert.
- Realtime auth denied spike alert.
- Readiness degraded alert.
- High memory/container restart alert.

### Dashboard Strategy (Future)

- API overview dashboard.
- Queue workers dashboard.
- Webhook delivery dashboard.
- Realtime health dashboard.
- Security/auth events dashboard.
- Database/cache performance dashboard.
- Release health dashboard.

No dashboard UI is added in this phase.

### Microservices Observability Rules

- Every service must emit structured logs.
- Every service must expose health/readiness endpoints.
- Every cross-service call/message must carry `correlation_id`.
- Every async consumer must log `event_id` / `idempotency_key` safely.
- Every service must define metric and alert ownership.
- No domain extraction is considered production-ready without observability readiness.

### Observability Anti-Patterns

- No microservice extraction without logs/metrics/health baselines.
- No raw payload logging.
- No secrets/tokens in log/trace/metric labels.
- No high-cardinality labels (raw email, token, request body fragments).
- No dashboard-only monitoring without actionable alerts.
- No alert spam without runbooks and ownership.
- No distributed tracing rollout before stable correlation ID propagation.
