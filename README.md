# Multi-Tenant Telephony Platform

[English](README.md) | [Українська](README_UA.md)

`multi-tenant-telephony-platform` is a portfolio-grade SaaS platform foundation for building multi-tenant telephony management, realtime communication, browser calling, and conference features.

The repository currently provides a mature application foundation based on a Laravel API, an Angular tenant dashboard, a Vue platform administration interface, RBAC, realtime chat, OpenAPI documentation, Docker, queues, monitoring, and automated tests.

> Telephony, multi-tenancy, FreeSWITCH integration, browser calling, conference rooms, and billing are being added incrementally. They are part of the project roadmap and must not be treated as fully implemented unless explicitly marked as completed.

## Current Foundation

- API-first Laravel backend under `/api/v1`
- Angular tenant-facing dashboard
- Vue platform administration interface
- Custom RBAC with backend permission enforcement
- Permission-aware Angular and Vue navigation
- Realtime chat with direct and group conversations
- Messages, participants, typing, presence, attachments, read states, webhooks, and external API support
- Laravel Reverb and Laravel Echo foundations
- OpenAPI documentation powered by Scramble
- Redis cache and queue foundations
- Queue worker and optional Horizon support
- Notifications and activity logs
- Health checks, structured logging, and monitoring foundations
- Docker-based local development environment
- Automated backend, Angular, and Vue tests
- Modular-monolith foundation with documented future extraction strategy

## Planned Platform Capabilities

The existing application will be extended in place with:

- Shared-database multi-tenancy
- Tenant memberships and tenant switching
- Platform and tenant role separation
- Tenant-isolated chat, realtime channels, queues, cache, storage, and audit logs
- Company and personal contacts
- Extensions and phone numbers
- Call logs and telephony statistics
- Ring groups, queues, and IVR configuration
- Fake PBX adapter for development and testing
- FreeSWITCH integration through provider contracts
- Browser softphone using SIP.js
- Call button inside direct and group chats
- Ad-hoc and permanent conference rooms
- Converting an active call into a conference
- Participant invitations from users, extensions, contacts, and external numbers
- Secure media transport
- Call and conference recordings
- Webhooks, usage accounting, billing foundations, and reports
- Telephony-focused monitoring and demo datasets

## Tech Stack

### Backend

- PHP 8.3
- Laravel 13
- MySQL 8
- Redis 7
- Laravel Sanctum
- Laravel Reverb
- Laravel Horizon
- dedoc/scramble

### Frontend

- Angular 21 for the tenant application
- Vue 3, Pinia, Vue Router, and Vite for platform administration
- SCSS

### Infrastructure and DevOps

- Docker Compose
- Nginx
- Queue worker
- Optional Horizon profile
- GitHub Actions CI foundation

## Application Responsibilities

### Laravel Backend

Laravel is the single backend API and authorization authority.

It is responsible for:

- authentication;
- roles and permissions;
- tenant isolation;
- chat and realtime authorization;
- telephony management;
- call and conference control;
- integrations;
- queues and events;
- notifications and activity logs;
- webhooks;
- billing and reporting;
- monitoring.

### Angular Tenant Application

Angular is the main user-facing application.

It is intended for:

- tenant dashboard;
- chat;
- contacts;
- browser softphone;
- calls and call history;
- conference rooms;
- extensions and phone numbers;
- queues and IVR;
- tenant reports and billing views;
- user settings.

### Vue Platform Administration

Vue is the platform administration interface.

It is intended for:

- tenant administration;
- platform users;
- global permission catalog;
- protected system roles;
- activity and support tools;
- queues and realtime monitoring;
- FreeSWITCH and integration health;
- platform-level statistics and billing administration.

## Architecture Overview

The current architecture is a modular monolith with API-first boundaries, service-layer organization, events, jobs, policies, and documented future extraction options.

The existing application is extended directly. Working backend, frontend, chat, RBAC, realtime, notification, queue, test, and documentation foundations are preserved rather than duplicated.

- [Backend architecture](backend/docs/architecture.md)
- [Future service extraction strategy](backend/docs/microservices.md)
- [Project documentation index](docs/README.md)

## Authentication and RBAC

The current foundation includes:

- session authentication;
- bearer and token support;
- Laravel Sanctum;
- roles and permissions;
- permission middleware;
- permission cache;
- backend policies;
- Angular permission guards;
- Vue permission guards;
- permission-aware navigation and actions.

Target RBAC rules for the telephony platform:

- a role is a collection of permissions;
- a user may have multiple roles;
- the backend remains the final authorization authority;
- unavailable functionality is hidden in the frontend;
- tenants may create custom roles from the system permission catalog;
- platform and tenant permissions will be separated;
- direct user permissions are not part of the target first-release product model.

## Chat and Realtime

The current chat foundation includes:

- direct conversations;
- group conversations;
- participants and participant roles;
- text messages;
- message editing and deletion;
- attachments;
- read and delivery states;
- typing indicators;
- presence;
- webhooks;
- external API support;
- realtime events through Reverb.

Planned chat and telephony integration includes:

- an audio-call button in direct chat;
- group-call creation from group chat;
- call and missed-call event messages;
- conference invitation messages;
- conference room chat;
- permission-controlled recording links.

## API Documentation

- Permission-aware documentation portal: `/docs/api/portal`
- User-filtered OpenAPI specification: `/docs/api.filtered.json`
- Full-access Swagger UI: `/docs/api`
- Raw OpenAPI specification: `/docs/api.json`

## Security Foundation

- Rate limiting
- Secure header policies
- Validation hardening
- Token security
- Backend authorization
- Realtime channel authorization
- Private attachment access
- Docker security review foundation
- Structured logs and sensitive-data handling

Planned VoIP security:

- HTTPS
- WSS
- TLS
- DTLS-SRTP
- encrypted SIP and PBX credentials
- private recording storage
- signed recording URLs
- future internal end-to-end encrypted call mode

## Local Development

Use the repository root as the working directory.

### 1. Prepare environment configuration

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 2. Start the current Docker stack

```bash
docker compose up -d
```

### 3. Install backend dependencies

```bash
docker compose exec backend composer install
```

### 4. Generate the Laravel application key

Run this only when `APP_KEY` has not already been configured:

```bash
docker compose exec backend php artisan key:generate
```

### 5. Run migrations and seeders

```bash
docker compose exec backend php artisan migrate --seed
```

Do not use `migrate:fresh` against an environment containing data that must be preserved.

### 6. Build the Vue administration frontend

```bash
docker compose exec backend npm ci
docker compose exec backend npm run build
```

### 7. Build the Angular tenant frontend

```bash
docker compose exec frontend npm ci
docker compose exec frontend npm run build
```

## Useful URLs

Default values from `docker-compose.yml` and `.env`:

- Laravel through Nginx: `http://localhost:8080`
- API base: `http://localhost:8080/api/v1`
- Vue administration development server: `http://localhost:5173`
- Angular tenant dashboard: `http://localhost:4200`
- API documentation portal: `http://localhost:8080/docs/api/portal`
- Full-access Swagger UI: `http://localhost:8080/docs/api`
- Public liveness endpoint: `http://localhost:8080/health`

Actual ports may differ when local environment values are changed.

## Testing

### Backend

```bash
docker compose exec backend php artisan test
docker compose exec backend composer test:openapi
```

### Vue administration frontend

Use the scripts defined in `backend/package.json`:

```bash
docker compose exec backend npm test
docker compose exec backend npm run build
```

### Angular tenant frontend

Use the scripts defined in `frontend/package.json`:

```bash
docker compose exec frontend npm test -- --watch=false
docker compose exec frontend npm run build
```

> Do not run multiple backend test processes in parallel against the same `saas_testing` database.

## Documentation Map

| Topic | Document |
| --- | --- |
| Documentation index | [docs/README.md](docs/README.md) |
| Architecture | [backend/docs/architecture.md](backend/docs/architecture.md) |
| OpenAPI preparation | [backend/docs/api/openapi-preparation.md](backend/docs/api/openapi-preparation.md) |
| OpenAPI generator | [backend/docs/api/openapi-generator.md](backend/docs/api/openapi-generator.md) |
| Security | [backend/docs/security.md](backend/docs/security.md) |
| Performance | [backend/docs/performance.md](backend/docs/performance.md) |
| Monitoring | [backend/docs/monitoring.md](backend/docs/monitoring.md) |
| Commands | [backend/docs/commands.md](backend/docs/commands.md) |
| Realtime | [backend/docs/realtime.md](backend/docs/realtime.md) |
| Docker | [backend/docs/docker.md](backend/docs/docker.md) |
| Deployment | [backend/docs/deployment.md](backend/docs/deployment.md) |
| CI/CD | [backend/docs/ci-cd.md](backend/docs/ci-cd.md) |
| Release process | [backend/docs/release.md](backend/docs/release.md) |
| Future service extraction | [backend/docs/microservices.md](backend/docs/microservices.md) |

## Production Notes

- Use `backend/.env.production.example` as a starting point
- Keep `APP_DEBUG=false`
- Use Redis for cache and queues
- Use HTTPS, secure cookies, and HSTS
- Do not expose MySQL or Redis publicly
- Run migrations intentionally during releases
- Store secrets outside the repository
- Protect private attachments and future recordings
- Review [backend/docs/deployment.md](backend/docs/deployment.md)

## Project Status

This repository is the active foundation for the Multi-Tenant Telephony Platform.

Currently implemented:

- Laravel API foundation;
- Angular tenant dashboard foundation;
- Vue platform administration foundation;
- authentication and RBAC;
- chat and realtime;
- notifications and activity logs;
- OpenAPI documentation;
- Docker, queues, monitoring, and testing foundations.

Planned and under incremental development:

- multi-tenancy;
- telephony management;
- FreeSWITCH integration;
- browser softphone;
- calls from chat;
- conference rooms;
- recordings;
- telephony billing and reports.

The project does not claim turnkey production readiness for every deployment environment. The current implementation remains a modular monolith, while future service extraction is documented as an architectural option.
