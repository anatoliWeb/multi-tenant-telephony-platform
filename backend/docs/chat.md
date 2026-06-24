# Chat

## Tenant Ownership

Chat is tenant-owned. Conversations are the root aggregate and every message, participant row, attachment, read record, typing event, presence subscription, and external delivery is resolved through the active tenant context.

Rules:

- conversations are created with the current tenant;
- messages inherit the conversation tenant;
- participants are validated against active membership in the same tenant;
- route binding fails closed outside the active tenant;
- cross-tenant reads and writes return safe 404/403 responses;
- tenant switches clear client-side chat state before a new tenant is loaded.

## Realtime

Chat uses the existing broadcast channels and the authorization callbacks now resolve the conversation inside the current tenant before allowing a private or presence subscription.

## External Integrations

External chat API calls and incoming webhooks are scoped to the integration tenant. Token and signature validation still run first, but the request is rejected if the conversation does not belong to the same tenant.

## Demo Data

Demo chat data is seeded deterministically so the same users can have isolated conversations in different tenants without duplicate rows on rerun.
