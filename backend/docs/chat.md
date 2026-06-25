# Chat

## Tenant Ownership

Chat is tenant-owned. Conversations are the root aggregate and every message, participant row, attachment, read record, typing event, presence subscription, webhook record, and external delivery is resolved through the active tenant context.

Rules:

- conversations are created with the current tenant;
- messages inherit the conversation tenant;
- participants are validated against active membership in the same tenant;
- route binding fails closed outside the active tenant;
- cross-tenant reads and writes return safe 404/403 responses;
- tenant switches clear client-side chat state before a new tenant is loaded.

## Schema Enforcement

Development validation on 2026-06-25 confirmed that the live database had not yet applied the first chat tenant backfill migration. Before the migration:

- all 11 chat tables already contained live data;
- `tenant_id` was absent on every tenant-owned chat table;
- no rows were deleted during the forward migration.

Applied migrations:

- `2026_06_24_000001_add_tenant_id_to_chat_tables`
- `2026_06_25_000000_enforce_chat_tenant_ownership_constraints`

Final enforced state:

- `conversations.tenant_id` is `NOT NULL`;
- `messages.tenant_id` is `NOT NULL`;
- `conversation_participants.tenant_id` is `NOT NULL`;
- `message_attachments.tenant_id` is `NOT NULL`;
- `message_reads.tenant_id` is `NOT NULL`;
- `message_device_reads.tenant_id` is `NOT NULL`;
- `message_deliveries.tenant_id` is `NOT NULL`;
- `external_message_mappings.tenant_id` is `NOT NULL`;
- `chat_webhook_endpoints.tenant_id` is `NOT NULL`;
- `chat_webhook_deliveries.tenant_id` is `NOT NULL`;
- `chat_user_devices.tenant_id` is `NOT NULL`.

All chat `tenant_id` foreign keys now use `ON DELETE RESTRICT` and `ON UPDATE CASCADE`. This keeps tenant ownership fail-closed at the database layer instead of silently nulling tenant boundaries.

## Live Backfill Counts

Before migration:

- conversations: `6`
- messages: `324`
- conversation_participants: `25`
- message_attachments: `0`
- message_reads: `895`
- message_device_reads: `1211`
- message_deliveries: `1350`
- external_message_mappings: `0`
- chat_webhook_endpoints: `0`
- chat_webhook_deliveries: `0`
- chat_user_devices: `15`

After migration and enforcement:

- no chat rows were deleted;
- every chat table above has `0` `tenant_id` null rows;
- message, participant, attachment, read, delivery, mapping, and webhook-delivery tenant mismatch counts are `0`.

Operational validation is available through:

```bash
php artisan chat:verify-tenant-integrity
php artisan chat:verify-tenant-integrity --json
```

## Realtime

Chat uses the existing broadcast channels and the authorization callbacks now resolve the conversation inside the current tenant before allowing a private or presence subscription.

Channel names were retained as:

- `private-chat.conversation.{id}`
- `presence-chat.{id}`
- legacy alias `chat.{id}`

Reason:

- conversation identifiers are globally unique integer primary keys;
- authorization resolves the conversation inside the active tenant before join;
- tenant identity is already present in structured logs and request context, so tenant-qualified channel names were not required for safe routing.

## External Integrations

External chat API calls and incoming webhooks are scoped to the integration tenant.

Fail-closed rules:

- webhook-token requests always bind tenant context from the owning endpoint;
- authenticated external API requests must provide an explicit `X-Tenant-ID` when the actor has more than one active tenant membership;
- authenticated requests may use the only active membership when tenant selection is unambiguous;
- explicit tenant-scoped chat permissions are still required after tenant resolution; platform chat permissions alone do not bypass tenant chat access;
- authenticated external API throttling now includes the explicit tenant header in the limiter key so one tenant cannot exhaust another tenant's bucket for the same actor;
- test-only compatibility fallback to the default tenant remains limited to unit-test runtime for legacy bare-user fixtures;
- production HTTP requests never fall back to the default tenant.

## Cross-Tenant Isolation Coverage

Latest dedicated regressions verify that:

- same-tenant chat route model binding resolves correctly from `X-Tenant-ID`;
- cross-tenant chat conversation lookup fails closed;
- platform chat permissions do not bypass tenant chat view access;
- platform external-chat permissions do not bypass tenant external-message access;
- external message mappings are unique per tenant, not globally;
- webhook endpoint listing and route binding remain inside the active tenant.

Reference matrix: `backend/docs/tenant-isolation-tests.md`.

## Demo Data

Demo chat data is seeded deterministically so the same users can have isolated conversations in different tenants without duplicate rows on rerun.

## Manual Validation

Manual browser validation was performed and confirmed by the project owner.

Confirmed scenarios:

- two independent browser sessions work;
- direct chat works inside Tenant A;
- messages and replies arrive through realtime without refresh;
- unread and read state works;
- typing behavior works;
- switching from Tenant A to Tenant B clears active conversation, conversation list, messages, unread state, typing state, and presence state;
- a Tenant A conversation URL is rejected under Tenant B;
- switching back to Tenant A restores only Tenant A data;
- logout clears tenant chat state.

## Stage 7 Backend Verification

Final backend verification on 2026-06-25 completed after clearing stale activity in the isolated `saas_testing` database.

Root cause of the earlier partial state:

- previous timed-out `php artisan test` runs left abandoned `saas_testing` migration and DDL work behind;
- development data was not part of the issue and was not reset.

Safe verification workflow:

- confirmed the resolved test context was environment `testing` on database `saas_testing`;
- re-validated the testing safety guard through `Tests\Unit\TestingDatabaseGuardTest`;
- inspected backend container processes and MySQL sessions;
- preserved backend, queue, Horizon, scheduler, Reverb, frontend, Redis, and development-database activity;
- waited for one still-active targeted rerun to finish its DDL work and confirmed the lingering test worker exited before starting the final clean reruns.

Verified backend totals:

- targeted tenant-isolation rerun: `7` passed, `0` failed, `0` skipped, `27` assertions, `426.78s`;
- external-chat regression rerun: `3` passed, `0` failed, `0` skipped, `27` assertions, `422.61s`;
- full backend suite rerun: `516` passed, `0` failed, `21` skipped, `16402` assertions, `850.41s`.

Development integrity recheck after the reruns:

- conversations `6`
- messages `324`
- conversation_participants `25`
- message_reads `895`
- message_device_reads `1211`
- message_deliveries `1350`
- chat_user_devices `15`
- `chat:verify-tenant-integrity --json` still reported zero null ownership rows, zero tenant mismatches, zero duplicate tenant-scoped device rows, and no data loss.

## Vue Admin Monitoring

The Vue admin application already has a real chat-monitoring feature in `resources/js/modules/chat-admin`.

Stage 7 adaptation:

- monitoring state now clears when the active tenant changes;
- realtime subscription is dropped before a new tenant view is loaded;
- a regression test covers the tenant-switch reset path.
