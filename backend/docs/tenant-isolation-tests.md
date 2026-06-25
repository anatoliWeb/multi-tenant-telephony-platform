# Tenant Isolation Tests

## Purpose

This document tracks the current cross-tenant isolation coverage and the boundaries that are intentionally enforced today.

## Invariants

- tenant identity must come from explicit `X-Tenant-ID` selection or one unambiguous active membership;
- unknown and inaccessible tenant identifiers must fail with the same safe authenticated response on tenant switching;
- tenant chat access requires active membership and tenant-scoped chat permissions;
- platform permissions alone do not authorize ordinary tenant chat or tenant external-message APIs;
- tenant-owned route model binding must fail closed outside the active tenant;
- tenant-scoped integration keys must not collide across tenants;
- explicit tenant headers must partition external chat rate limiting for the same authenticated actor;
- realtime and broadcast authorization must resolve the requested conversation inside the active tenant.

## Coverage Matrix

| Area | Resource or behavior | Tenant-owned | List test | Detail test | Create test | Update test | Delete test | Cache test | Realtime test | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TenantContext | header-driven tenant resolution and fail-closed context requirements | Yes | PASS | PASS | N/A | N/A | N/A | N/A | N/A | PASS |
| memberships | active membership gating for tenant APIs | Yes | PASS | PASS | N/A | N/A | N/A | N/A | N/A | PASS |
| roles | tenant-role assignment by tenant scope | Yes | N/A | PASS | PASS | N/A | N/A | PASS | N/A | PASS |
| permission resolution | tenant permissions resolve only inside active tenant scope | Yes | N/A | PASS | N/A | N/A | N/A | PASS | N/A | PASS |
| permission cache | tenant cache keys isolate tenant A from tenant B | Yes | N/A | N/A | N/A | N/A | N/A | PASS | N/A | PASS |
| tenant switching | unknown and inaccessible tenant identifiers are indistinguishable | Yes | N/A | PASS | PASS | N/A | N/A | N/A | N/A | PASS |
| conversations | route binding, listing, and detail views stay within active tenant | Yes | PASS | PASS | PASS | N/A | N/A | N/A | PASS | PASS |
| messages | chat send and external send stay inside active tenant | Yes | N/A | PASS | PASS | PASS | PASS | N/A | PASS | PASS |
| participants | participant visibility and membership enforcement stay tenant-scoped | Yes | PASS | PASS | PASS | PASS | PASS | N/A | PASS | PASS |
| attachments | cross-tenant attachment access is denied through tenant-owned message boundaries | Yes | N/A | PASS | PASS | PASS | PASS | N/A | N/A | PASS |
| read state | read receipts and per-message reads stay conversation-tenant scoped | Yes | N/A | PASS | PASS | PASS | N/A | N/A | N/A | PASS |
| unread counts | unread aggregation uses current tenant conversation scope only | Yes | PASS | PASS | N/A | PASS | N/A | N/A | N/A | PASS |
| device reads | device read state is tenant-owned and integrity-audited | Yes | N/A | PASS | PASS | PASS | N/A | N/A | N/A | PASS |
| typing | typing throttles and visibility stay inside current tenant conversation scope | Yes | N/A | PASS | PASS | PASS | N/A | N/A | PASS | PASS |
| presence | presence join and leave stay inside current tenant conversation scope | Yes | N/A | PASS | PASS | PASS | N/A | N/A | PASS | PASS |
| broadcast authorization | private and presence chat channels authorize inside active tenant only | Yes | N/A | PASS | N/A | N/A | N/A | N/A | PASS | PASS |
| external message mappings | same provider and external id can exist once per tenant | Yes | N/A | PASS | PASS | N/A | N/A | N/A | N/A | PASS |
| webhook endpoints | list and route binding stay within active tenant | Yes | PASS | PASS | PASS | PASS | PASS | N/A | N/A | PASS |
| webhook deliveries | delivery lookups and integrity checks stay tied to tenant-owned endpoints and messages | Yes | PASS | PASS | PASS | N/A | N/A | N/A | N/A | PASS |
| chat user devices | device uniqueness is tenant-scoped per user and device key | Yes | N/A | PASS | PASS | PASS | N/A | N/A | N/A | PASS |
| demo seed data | deterministic tenants and personas stay separated across demo fixtures | Yes | PASS | PASS | PASS | N/A | N/A | N/A | N/A | PASS |
| notifications | legacy notification tenancy | No | N/A | N/A | N/A | N/A | N/A | N/A | N/A | N/A |
| activity logs | legacy activity-log tenancy | No | N/A | N/A | N/A | N/A | N/A | N/A | N/A | N/A |
| queue propagation | tenant context propagation through queued jobs | No | N/A | N/A | N/A | N/A | N/A | N/A | N/A | N/A |
| scheduler propagation | tenant context propagation through scheduled commands | No | N/A | N/A | N/A | N/A | N/A | N/A | N/A | N/A |

## Added Security Regression Tests

- `Tests\Feature\Tenancy\Isolation\TenantContextIsolationTest`
- `Tests\Feature\Tenancy\Isolation\TenantChatIsolationTest`
- `Tests\Feature\Tenancy\Isolation\TenantExternalIntegrationIsolationTest`

## Existing Suites That Backstop Isolation

- `Tests\Feature\Api\TenantAwareRbacTest`
- `Tests\Feature\Api\V1TenantContextTest`
- `Tests\Feature\Chat\ChatExternalApiMessageSendingTest`
- `Tests\Feature\Chat\ChatIncomingWebhookEndpointTest`
- `Tests\Feature\Chat\ChatExternalWebhookFoundationTest`
- `Tests\Feature\Chat\ChatPresenceChannelTest`
- `Tests\Feature\Chat\ChatRealtimeEventsTest`
- `Tests\Feature\Chat\ChatAttachmentApiTest`
- `Tests\Feature\Chat\ChatDeviceReadStateApiTest`
- `Tests\Feature\Seeders\SeederArchitectureTest`

## Latest Verification

- targeted isolation suite: `7` passed, `0` failed, `0` skipped, `27` assertions;
- full backend suite: `516` passed, `0` failed, `21` skipped, `16402` assertions;
- `php artisan chat:verify-tenant-integrity --json` reported zero tenant mismatches and preserved live development counts:
  - conversations `6`
  - messages `324`
  - conversation_participants `25`
  - message_reads `895`
  - message_device_reads `1211`
  - message_deliveries `1350`
  - chat_user_devices `15`
