# ADR 0002: Laravel Remains the Single Backend API

- Status: Accepted
- Date: 2026-06-23

## Context

The platform already routes Angular and Vue traffic through one Laravel runtime.

## Decision

Laravel remains the single backend API and the single source of backend business rules.

## Consequences

- Both frontends consume the same API contract.
- Authorization stays centralized in Laravel.
- Future services must be planned as contracts first, not as ad hoc side systems.
