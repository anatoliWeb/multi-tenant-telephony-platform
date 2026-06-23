# ADR 0005: Use Shared-Database Multi-Tenancy

- Status: Accepted
- Date: 2026-06-23

## Context

The next major platform step is tenant isolation without discarding the current baseline or introducing operational overhead from a split database model too early.

## Decision

Multi-tenancy will use a shared database with tenant-owned rows carrying `tenant_id`.

## Consequences

- Tenant isolation must be enforced in data access and authorization.
- Queries, policies, jobs, and channels must become tenant-aware.
- Migration work must remain incremental and testable.
