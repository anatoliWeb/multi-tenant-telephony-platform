# Architecture

## Overview

This architecture describes the current foundation for Multi-Tenant Telephony Platform, where a stateless Laravel API serves separate Angular and Vue clients.

The project is designed around clear separation between API and UI, with a strong focus on scalability, maintainability, and incremental delivery. Backend and frontend evolve independently through explicit API contracts, which makes the system easier to test, extend, and operate.

---

## High-Level Architecture

Angular Dashboard / Vue Admin -> Nginx -> Laravel API -> Services -> Database / Redis

Layer responsibilities:
- `Angular Dashboard / Vue Admin`: render UI, handle user interaction, and call backend APIs.
- `Nginx`: entry point and reverse proxy to PHP-FPM backend.
- `Laravel API`: stateless HTTP layer, validation, authorization, response contracts.
- `Services layer`: domain/business logic orchestration, kept outside controllers.
- `Data layer (MySQL/Redis)`: persistence, cache primitives, queue-ready infrastructure.

Stateless API design means each request contains all context needed for authorization and execution (typically Bearer token + payload), without relying on frontend session state.

---

## Components

### Backend (Laravel)

Responsible for:
- REST API endpoints
- authentication and authorization
- business logic orchestration
- admin-facing server functionality

Internal flow:
- Controllers (HTTP entry points)
- Services (business rules and orchestration)
- Models (persistence and relationships)

Backend flow details:
- `Controller -> Service -> Model -> DB`
- Controllers stay thin to keep HTTP concerns isolated.
- Services hold use-case logic to avoid duplication across controllers.
- Models stay focused on data access and relationships, not workflow logic.

### Frontend Clients

Responsible for:
- page-level UX and routing
- reusable components and design system
- API consumption via service layer
- permission-aware UI behavior

Frontend structure (high level):
- `pages/`: route-level screens (Dashboard, Users, etc.)
- `components/`: reusable UI blocks (DataTable, Modal, Form, Header)
- `services/`: API contract layer (request functions, resource services)
- `hooks/`: reusable async/state abstractions (meta, API helpers)
- `contexts/`: global cross-cutting state (e.g., global loader)

Frontend architecture choices:
- DataTable abstraction centralizes search/sort/pagination/actions behavior.
- Global loader provides consistent UX for long-running mutations.
- Form flow handles `422` validation errors with field-level feedback.

### Database (MySQL)

Used for:
- persistent domain data
- users, roles, permissions, tokens, activity logs
- relational integrity for RBAC and audit features

Data is persisted through Docker volumes.

### Redis

Used for:
- cache and queue-ready infrastructure
- fast in-memory operations for future async workloads

### Nginx

Acts as:
- reverse proxy
- backend entry point
- static/public HTTP gateway

### WebSocket (optional)

Reserved for:
- future real-time updates
- event-driven UX scenarios

---

## Authentication

Authentication is token-based using Laravel Sanctum:
1. Client requests token via API login endpoint.
2. Token is issued for authenticated user.
3. Client sends `Authorization: Bearer <token>` for protected requests.
4. Sanctum validates token per request.

This keeps API access stateless and compatible with SPA/mobile clients.

## Authorization (RBAC)

RBAC is implemented with roles and permissions:
- roles group common access policies (`admin`, `manager`, `user`)
- permissions define granular actions (`users.view`, `users.create`, etc.)
- user access is calculated from role permissions plus optional direct permissions

Enforcement model:
- Backend is the source of truth via permission middleware/authorization checks.
- Frontend consumes permissions for UI visibility (hide actions user cannot perform).
- UI checks improve UX only; they never replace backend authorization.

---

## Activity Logging

Activity logging follows an event-driven auditing pattern:
- `ActivityService` is the centralized logging entry point.
- Model observers capture important model lifecycle events.
- Logs are stored as an audit trail with action, actor, and metadata.
- Write operations are dispatched to queue jobs for non-blocking API responses.

This approach improves traceability and supports operational diagnostics and dashboard activity feeds.

---

## Queue System

The system uses asynchronous background processing for non-critical write operations.

- Queue backend: Redis
- Job example: `LogActivityJob`
- Activity queue: `activity` (named queue)
- Worker runtime: Laravel `queue:work`
- Process manager: Supervisor
- Worker priority order: `--queue=activity,default`

Queue separation strategy:
- `activity`: audit trail and activity log writes
- `default`: generic application jobs
- planned queues: `notifications`, `realtime`

Why this matters:
- API responses stay fast because logging is processed out of request cycle.
- Activity logging is isolated from future email/notification/realtime workloads.
- Worker restarts are handled automatically by Supervisor.
- Retry/timeout behavior is controlled in worker command flags.

Scaling model:
- Start with one worker handling `activity,default`.
- Split workers by queue as load grows (for example dedicated `activity` and `notifications` workers).
- This allows independent scaling per workload type without changing API contracts.

---

## Data Flow

Example request flow (`GET /api/users`):
1. SPA sends request with Bearer token.
2. Nginx routes request to Laravel (PHP-FPM).
3. Global/app middleware executes (CORS, auth, permission checks).
4. Route resolves controller action.
5. FormRequest/validation rules are applied where relevant.
6. Controller delegates use case to Service.
7. Service executes business logic through models/repositories and DB.
8. Response is transformed to API JSON contract and returned to frontend.

This flow keeps transport, policy, domain logic, and persistence concerns separated.

---

## Security

Security baseline includes:
- Sanctum token authentication
- password hashing through Laravel hashing facilities
- validation-first request handling (FormRequest)
- RBAC authorization enforcement on protected routes
- explicit API contracts to reduce unsafe implicit behavior
- login endpoint protection strategy ready for rate limiting

---

## Key Design Decisions

- API-first architecture for decoupled clients
- strict separation of concerns (Controller/Service/Model)
- stateless auth model suitable for SPA integrations
- RBAC as core access control strategy
- centralized activity logging for auditability
- Dockerized local environment for reproducible setup

---

## Future Improvements

- Queue workers and async jobs with Redis
- Caching layer for expensive read endpoints
- API versioning strategy (`/api/v1`)
- Monitoring/observability stack (metrics, traces, structured logs)
- CI quality gates for tests, linting, and build validation

---

## Notes

- The system is intentionally modular and extensible.
- Current architecture prioritizes long-term maintainability over short-term speed.

---

<!-- WHY:
Improves developer navigation and onboarding experience.
-->
## Related Documentation

- [Architecture](./architecture.md)
- [Architecture Pack](./architecture/README.md)
- [API](./api.md)
- [Commands](./commands.md)
- [Coding Standards](./coding-standards.md)
- [Main Docs](./README.md)
