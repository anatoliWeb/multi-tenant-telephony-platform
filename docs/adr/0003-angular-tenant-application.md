# ADR 0003: Angular Is the Tenant Application

- Status: Accepted
- Date: 2026-06-23

## Context

Angular already serves the tenant dashboard, chat, and realtime client experience.

## Decision

Angular is the tenant-facing application.

## Consequences

- Tenant workflows stay in Angular.
- Angular guards improve UX but do not replace backend authorization.
- Future tenant telephony UI should extend Angular, not duplicate it in Vue.
