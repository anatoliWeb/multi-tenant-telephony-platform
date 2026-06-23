# ADR 0001: Extend the Existing Application In Place

- Status: Accepted
- Date: 2026-06-23

## Context

The repository already contains a working Laravel backend, Angular tenant frontend, Vue administration frontend, chat, RBAC, realtime, queues, and documentation baseline.

## Decision

We will extend the existing application in place instead of creating a second repository or rewriting the baseline.

## Consequences

- The release-ready baseline stays intact.
- Architecture changes must be incremental and well documented.
- New domains must respect existing contracts and tests.
