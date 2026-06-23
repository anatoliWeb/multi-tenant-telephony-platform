# Domain Structure (Modular Monolith Preparation)

## Purpose
This directory prepares the backend for domain-oriented growth while keeping the application as a single Laravel codebase.

The current project is intentionally a modular monolith, not a microservice system.  
All domains remain inside one deployable application to keep development fast, operations simple, and architecture maintainable.

## Why Prepare Domains Early
- Clear boundaries reduce coupling between features.
- Teams can evolve logic by domain instead of by technical layer only.
- Contracts become easier to stabilize for API, events, and background jobs.
- Future extraction to services is safer when domain responsibilities are already separated.

## Why This Is Not Overengineering
- No custom package system.
- No module loader.
- No runtime container complexity.
- No forced full DDD migration.

We only add lightweight structure now so future refactors have a predictable path.

## Current Direction
Each domain has placeholder folders:
- `Actions/`: use-case oriented operations.
- `DTO/`: explicit data contracts between layers.
- `Events/`: domain events with clear business meaning.
- `Services/`: domain-specific service logic.

## Intended Domain Boundaries
- `Auth`: authentication and session/token lifecycle concerns.
- `User`: user lifecycle, profile, and user-centric policies.
- `Token`: personal access token management and related lifecycle logic.
- `Shared`: cross-domain utilities/contracts that do not belong to one domain.

## Future Evolution (Incremental)
As the codebase grows, services should move closer to their domain folders, and new features should prefer domain-local actions/events.

Likely next domain candidates:
- `Notification`
- `Realtime`
- `Activity`

This allows incremental migration without breaking existing namespaces or controllers.

## Microservice Readiness (Later, Optional)
If extraction is needed in the future, domain boundaries created here provide a practical starting point:
- clearer ownership,
- lower coupling,
- better event contract stability.

Extraction is a business/scale decision for later phases, not a requirement now.
