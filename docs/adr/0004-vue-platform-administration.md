# ADR 0004: Vue Is the Platform Administration Application

- Status: Accepted
- Date: 2026-06-23

## Context

Vue already serves the platform administration experience and operational dashboards.

## Decision

Vue is the platform administration application.

## Consequences

- Platform users and support tooling stay in Vue.
- Vue continues to use the same Laravel API as Angular.
- Tenant-facing product features should not be duplicated in Vue without a documented reason.
