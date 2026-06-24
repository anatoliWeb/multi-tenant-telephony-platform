# ADR 0007: Exclude Direct User Permissions From the First Release

- Status: Accepted
- Date: 2026-06-23

## Context

The first-release access model needs to stay predictable while tenant isolation and role boundaries are introduced.

## Decision

Direct user permissions will not be part of the target first-release model.

## Consequences

- Role-based assignment remains the primary access path.
- Frontend permission UX can stay simpler.
- Legacy compatibility features must not become the basis of the new model.
- Tenant permission resolution must ignore legacy direct grants and denies.
