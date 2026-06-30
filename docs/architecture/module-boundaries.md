# Module Boundaries

## Purpose

This document defines the target modular-monolith module boundaries for future work.

The boundaries below are planning rules for new code. They do not require a wholesale codebase reorganization now.

## Shared Rules

1. A module does not write directly to another module's internal tables.
2. Cross-module behavior uses application services, events, or contracts.
3. Controllers remain thin.
4. Provider-specific code stays in `Integrations`.
5. FreeSWITCH-specific logic must not leak into telephony domain services.
6. Shared code must remain generic.
7. Shared must not become a dumping ground.
8. Frontend-specific concepts must not leak into domain models.
9. Tenant ownership is enforced inside backend data access and authorization.
10. Realtime messages are outputs of domain/application behavior, not the source of business truth.

## Identity

- Responsibility: user identity, authentication, session/token lifecycle.
- Owned entities: users, auth tokens, identity snapshots.
- Public application services: `AuthService`, `TokenService`, `UserService`.
- Events emitted: `TokenCreated`, `TokenRevoked`, `UserCreated`, `UserUpdated`.
- Contracts consumed: AccessControl, Monitoring, Notifications.
- Allowed dependencies: Shared, AccessControl.
- Forbidden dependencies: Chat persistence, telephony execution, provider adapters.

## Tenancy

- Responsibility: tenants, memberships, active tenant context, tenant switching.
- Owned entities: tenants, tenant memberships, tenant context snapshots.
- Public application services: `TenantService`, `TenantMembershipService`, `TenantContext`.
- Events emitted: tenant created/updated/suspended, membership invited/accepted/changed.
- Contracts consumed: Identity, AccessControl, Chat, Notifications, Billing, Reporting.
- Allowed dependencies: Identity, AccessControl, Shared.
- Forbidden dependencies: provider-specific telephony code, frontend UI state.
- Runtime boundary: runtime tenancy services may read and resolve context, but deterministic tenant creation and membership repair belong to seeding services only.
- Frontend boundary: Angular owns tenant navigation state and tenant preload
  timing, while Vue Admin may hold a support-oriented tenant selector without
  becoming the source of truth for tenant authorization.

## AccessControl

- Responsibility: platform roles, tenant roles, permissions, permission caches, authorization policies.
- Owned entities: roles, permissions, role/permission assignments, cache metadata.
- Public application services: `PermissionService`, `RoleService`, `PermissionCacheService`.
- Events emitted: permission changed, role permissions changed.
- Contracts consumed: Identity, Tenancy, Monitoring.
- Allowed dependencies: Identity, Tenancy, Shared.
- Forbidden dependencies: direct writes into Chat, Billing, or Telephony tables.
- Platform Admin tenant access is centralized here through tenant permission resolution, not ad-hoc controller or policy bypasses.
- Vue Admin support pages use platform permissions for visibility, but tenant
  feature data still depends on active tenant context and tenant-scoped policy
  enforcement at the API layer.

## Contacts

- Responsibility: people and organizations that can be called or messaged.
- Owned entities: contacts, contact phones, contact emails, tags, notes, favorites.
- Public application services: `ContactService`, `ContactQueryService`, `ContactImportService`, `ContactExportService`, `PhoneNumberNormalizer`.
- Events emitted: contact created/updated/imported.
- Contracts consumed: Tenancy, AccessControl, Telephony.
- Allowed dependencies: Identity, Tenancy, Shared.
- Forbidden dependencies: provider adapters and UI-specific view logic.

## Chat

- Responsibility: conversations, messages, participants, attachments, read/delivery state, presence, typing, chat webhooks.
- Owned entities: conversations, messages, participants, attachments, deliveries, reads, webhook metadata.
- Public application services: `ChatConversationService`, `ChatMessageService`, `ChatReadStateService`, `ChatAttachmentService`, `ChatPresenceService`, `ChatWebhookDeliveryService`.
- Events emitted: chat conversation/message/participant/typing/presence/webhook events.
- Contracts consumed: Identity, Tenancy, AccessControl, Notifications, Audit, Realtime.
- Allowed dependencies: Identity, Tenancy, AccessControl, Notifications, Audit, Realtime, Webhooks.
- Forbidden dependencies: direct telephony provider classes or cross-module DB writes.

## Telephony

- Responsibility: telephony domain behavior and provider-neutral call lifecycle.
- Owned entities: extensions, extension credentials, active call sessions, call legs, routing decisions, extension bindings, and tenant-scoped IVR configuration.
- Public application services: `TelephonyService`, `ExtensionService`, `ExtensionProvisioningService`, `ExtensionCredentialService`, `ExtensionQueryService`, `CallRecordingService`, `IvrMenuService`, `IvrRoutingService`.
- Events emitted: call started, call answered, call ended, routing changed, IVR menu changed, provider-neutral call lifecycle notifications for history recording.
- Contracts consumed: Integrations, Contacts, AccessControl, Tenancy.
- Allowed dependencies: Shared, Integrations, Contacts, AccessControl.
- Forbidden dependencies: direct FreeSWITCH class references.

## IVR

- Responsibility: tenant-scoped IVR menus, IVR options, routing validation, timeout behavior, and invalid-input behavior.
- Owned entities: IVR menus, IVR options, routing plans.
- Public application services: `IvrMenuService`, `IvrRoutingService`, `IvrMenuQueryService`.
- Events emitted: IVR menu created, IVR menu updated, IVR menu deleted, IVR option changed.
- Contracts consumed: Telephony, Tenancy, AccessControl.
- Allowed dependencies: Shared, Telephony, Tenancy, AccessControl.
- Forbidden dependencies: direct provider execution, media playback, or other tenant's routing graphs.
- The IVR module is intentionally configuration-first: it validates routing and returns dry-run plans for later call-control integration.

## CallManagement

- Responsibility: call logs, call summaries, call history, agent activity around calls.
- Owned entities: call logs, call events, call outcomes, call summaries, routing history.
- Public application services: `CallLogService`, `CallLifecycleService`, `CallEventService`, `CallQueryService`, `CallStatisticsService`.
- Events emitted: call log created, call event recorded, call disposition updated, call statistics queried.
- Contracts consumed: Telephony, Tenancy, AccessControl, Reporting.
- Allowed dependencies: Telephony, Reporting, Shared.
- Forbidden dependencies: provider-specific call execution code.

## Conferences

- Responsibility: conference rooms, participant lifecycle, conference recordings, moderator rules.
- Owned entities: conference rooms, conference participants, conference recordings.
- Public application services: `ConferenceService`, `ConferenceParticipantService`, `ConferenceRecordingService`.
- Events emitted: conference created, participant joined, participant left, recording ready.
- Contracts consumed: Telephony, AccessControl, Tenancy, Integrations.
- Allowed dependencies: Telephony, Integrations, Shared.
- Forbidden dependencies: direct SIP/RTP implementation details.

## Integrations

- Responsibility: provider contracts, adapters, credential handling, normalized DTOs, retry policy boundaries.
- Owned entities: provider connections, adapter metadata, integration credentials.
- Public application services: `TelephonyProvider`, `EndpointProvisioningProvider`, `CallControlProvider`, `ConferenceControlProvider`, `TelephonyHealthProvider`.
- Events emitted: integration state changes, provider health changes, delivery outcomes.
- Contracts consumed: Telephony, Conferences, Webhooks, Billing, Monitoring.
- Allowed dependencies: Shared, Queue, Monitoring.
- Forbidden dependencies: leaking provider specifics into domain services.

## Webhooks

- Responsibility: webhook endpoints, subscriptions, signatures, delivery logs, retries, replay protection.
- Owned entities: webhook endpoints, webhook deliveries, delivery attempts.
- Public application services: `WebhookEndpointService`, `WebhookDeliveryService`, `WebhookSigningService`.
- Events emitted: webhook queued, webhook delivered, webhook failed, webhook replayed.
- Contracts consumed: Chat, Integrations, Monitoring.
- Allowed dependencies: Queue, Shared, Monitoring.
- Forbidden dependencies: raw provider payloads in domain events.

## Billing

- Responsibility: accounts, rate plans, usage records, cost calculations, billing visibility.
- Owned entities: billing accounts, rates, usage records, balances, invoices.
- Public application services: `BillingAccountService`, `UsageRatingService`, `BillingQueryService`.
- Events emitted: usage recorded, rating completed, billing alert raised.
- Contracts consumed: Tenancy, CallManagement, Conferences, Reporting.
- Allowed dependencies: Shared, AccessControl, Reporting.
- Forbidden dependencies: direct telephony provider classes.

## Reporting

- Responsibility: query-only reporting, exports, analytics views, scheduled report generation.
- Owned entities: report definitions, export jobs, materialized read models.
- Public application services: `ReportQueryService`, `ReportExportService`.
- Events emitted: report generated, export completed.
- Contracts consumed: Chat, CallManagement, Billing, Tenancy, Monitoring.
- Allowed dependencies: Shared, Queue.
- Forbidden dependencies: mutating source-module tables.

## Notifications

- Responsibility: notification inbox, delivery status, read state, user preferences.
- Owned entities: notifications, preferences, delivery records.
- Public application services: `NotificationService`, `NotificationPreferenceService`.
- Events emitted: notification created, notification read, notification preference changed.
- Contracts consumed: Identity, Tenancy, Chat, Audit, Realtime.
- Allowed dependencies: Shared, Queue, Realtime.
- Forbidden dependencies: core business decisions from other modules.

## Audit

- Responsibility: activity logs, compliance trail, operational audit records.
- Owned entities: activity logs, audit trail entries.
- Public application services: `ActivityService`, `AuditTrailService`.
- Events emitted: activity logged, audit exported.
- Contracts consumed: all domain modules as event sources.
- Allowed dependencies: Shared, Queue, Realtime.
- Forbidden dependencies: mutating source domain state.

## Monitoring

- Responsibility: health checks, readiness checks, queue/realtime diagnostics, operational signals.
- Owned entities: health summaries, diagnostic snapshots.
- Public application services: `MonitoringHealthService`, `SystemHealthService`, `RealtimeLogService`.
- Events emitted: monitoring alerts, readiness snapshots.
- Contracts consumed: all runtime modules as needed.
- Allowed dependencies: Shared, Queue, Cache.
- Forbidden dependencies: secrets, raw payloads, and business mutations.

## Shared

- Responsibility: generic helpers, base DTOs, reusable response envelopes, common value objects.
- Owned entities: only generic, non-domain-specific helpers.
- Public application services: shared utilities only when truly cross-cutting.
- Events emitted: none by default.
- Contracts consumed: all modules.
- Allowed dependencies: standard framework primitives and pure utilities.
- Forbidden dependencies: module-specific business logic, provider adapters, or UI concerns.

## Phone Numbers

- Responsibility: tenant DID inventory, user assignment, primary DID selection, inbound DID ownership resolution foundation.
- Owned entities: `PhoneNumber`.
- Public application services: `PhoneNumberService`, `PhoneNumberAssignmentService`, `PhoneNumberQueryService`, `UserPrimaryDidResolver`, `InboundDidResolver`.
- Events emitted: none yet.
- Contracts consumed: shared phone-number normalization and generic telephony abstractions only.
- Allowed dependencies: Shared, Tenancy, RBAC, Telephony contracts.
- Forbidden dependencies: direct FreeSWITCH persistence, SIP.js, real routing logic.
