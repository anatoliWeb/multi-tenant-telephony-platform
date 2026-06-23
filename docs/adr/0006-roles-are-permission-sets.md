# ADR 0006: Roles Are Permission Sets

- Status: Accepted
- Date: 2026-06-23

## Context

The current platform already models access around roles and permissions.

## Decision

Roles represent reusable permission sets rather than standalone privilege containers.

## Consequences

- Permission assignment stays explicit.
- Platform and tenant role catalogs can be separated later.
- Permission checks remain the backend source of truth.
