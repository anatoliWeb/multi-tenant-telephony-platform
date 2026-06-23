# Deployment

## Purpose

This document is a production deployment preparation guide for Multi-Tenant Telephony Platform.

It is provider-neutral: it does not define a specific cloud platform, registry, Kubernetes setup, or automatic production pipeline. Use it as a practical checklist for preparing an environment-specific deployment.

## Deployment Model

The project currently uses a Docker-based deployment foundation:

- Laravel backend/API as the core application runtime
- Vue Admin built from the backend workspace
- Angular Dashboard built from the frontend workspace
- Nginx as HTTP reverse proxy / web entrypoint
- MySQL as primary relational database
- Redis for cache and queue workloads
- Queue workers for async jobs and webhook delivery
- Optional Horizon profile for queue visibility
- Laravel Reverb for websocket/realtime transport

The default `docker-compose.yml` is development-oriented. Production deployments should be based on environment-specific infrastructure and can use `docker-compose.prod.example.yml` only as a template.

## Environments

### Local

- Purpose: day-to-day development.
- Env source: local `.env` / `backend/.env` values.
- Secrets handling: local-only sample/dev values.
- Debug/log level: `APP_DEBUG=true`, verbose local logs are acceptable.

### Testing / CI

- Purpose: deterministic test runs and contract checks.
- Env source: `backend/.env.testing` plus CI workflow overrides.
- Secrets handling: testing-only values, never production credentials.
- Debug/log level: testing-safe output; DB should be isolated from development DB.

### Staging

- Purpose: production-like validation before release.
- Env source: environment-injected values based on production template.
- Secrets handling: secrets manager, CI/CD variables, or host-level env injection.
- Debug/log level: `APP_DEBUG=false`, logs close to production settings.

### Production

- Purpose: real users and real data.
- Env source: environment-specific secret/config injection based on `backend/.env.production.example`.
- Secrets handling: no secrets committed, no secrets baked into images.
- Debug/log level: `APP_DEBUG=false`, `LOG_LEVEL=info` or stricter.

## Production Environment Variables

Use `backend/.env.production.example` as the baseline template.

Required production rules:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` generated per environment
- `APP_URL` points to the public HTTPS application URL
- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `LOG_CHANNEL=stack`
- `LOG_STACK=stderr`
- `SESSION_SECURE_COOKIE=true`
- `SECURITY_HSTS_ENABLED=true` only behind HTTPS
- `API_DOCS_LOCAL_BYPASS=false`

Do not commit real values for DB passwords, Redis passwords, Reverb secrets, API keys, tokens, or app keys.

## Docker Production Template

`docker-compose.prod.example.yml` is a template, not a complete production deployment.

Production expectations:

- no public DB/Redis ports unless intentionally restricted by network controls
- no source bind mounts for application runtime images
- `restart: unless-stopped` or equivalent process supervision
- healthchecks for runtime dependencies
- bounded Docker log rotation
- `env_file` or environment injection for deployment-specific values
- secrets injected at runtime, not copied into images

Validate the template:

```bash
docker compose -f docker-compose.prod.example.yml config
```

## Build and Release Flow

Recommended high-level flow:

1. Ensure CI is green.
2. Build backend/frontend assets or images for the target environment.
3. Validate Docker compose configuration.
4. Review migrations and backup requirements.
5. Run migrations intentionally.
6. Restart app and queue workers.
7. Run health checks and smoke checks.
8. Verify API docs access policy and realtime/queue basics.

Validation commands are centralized in `backend/docs/commands.md`.

Release process details are in `backend/docs/release.md`.

## Database Migrations

Migrations must be explicit release actions.

Rules:

- take a backup before destructive or high-risk migrations
- review schema-impact before release
- run production migrations intentionally with `php artisan migrate --force`
- do not run unknown migrations blindly
- prefer forward-fix migrations for production incidents where possible
- use rollback migrations only after review and with a known data-safety plan

## Queue Workers

Production baseline:

- Redis queue connection (`QUEUE_CONNECTION=redis`)
- failed jobs driver enabled (`QUEUE_FAILED_DRIVER=database-uuids`)
- queue workers supervised by Docker/supervisor/platform process manager
- queue workers restarted after deploy

Useful commands:

```bash
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

Queue priorities and worker tuning are documented in `backend/docs/performance.md`.

## Reverb / Realtime

Realtime deployment needs TLS-aware websocket configuration.

Production environment variables should align:

- `REVERB_HOST`
- `REVERB_PORT`
- `REVERB_SCHEME`
- `REVERB_BROADCAST_HOST`
- frontend `VITE_REVERB_*` values

Deployment notes:

- proxy websocket traffic through the public HTTPS edge
- keep channel authorization in the backend/domain policy layer
- do not make private/presence channels public
- verify frontend websocket URL configuration after release

## API Docs Access

OpenAPI docs must remain protected in production:

- `/docs/api/portal` requires `api.docs.view`
- `/docs/api.filtered.json` requires `api.docs.view`
- raw `/docs/api` requires `api.docs.view.full` or admin/full docs access
- raw `/docs/api.json` requires `api.docs.view.full` or admin/full docs access
- `API_DOCS_LOCAL_BYPASS=false` in production

## Health Checks

Liveness and readiness are intentionally separate:

- `/health`: public liveness check, safe minimal response
- `/api/v1/system/health`: protected readiness/status endpoint
- Docker healthchecks: lightweight container/process checks

Readiness should validate dependencies such as database/cache/queue without exposing credentials, stack traces, or raw environment values.

## Security Checklist

- `APP_DEBUG=false`
- HTTPS reverse proxy in front of the app
- HSTS enabled only behind HTTPS
- secure cookies enabled
- rate limiting enabled
- secure headers enabled
- DB/Redis not publicly exposed
- secrets not committed and not baked into images
- token/authorization/cookie logging prohibited
- Nginx denies hidden files (`.env`, `.git`, etc.)
- Docker images do not copy `.env`
- API docs raw UI/spec restricted to full docs users

Detailed security policy: `backend/docs/security.md`.

## Rollback Strategy

Application rollback baseline:

1. Roll back to previous known-good tag/image.
2. Restart app and queue workers.
3. Run liveness/readiness checks.
4. Verify auth/API/docs/queue/realtime smoke paths.

Database rollback caution:

- schema rollback is not always safe with production data
- prefer forward-fix migrations when possible
- if rollback is required, use reviewed migration steps and verified backups

## Production Checklist

- [ ] Production env prepared from `backend/.env.production.example`
- [ ] Secrets injected externally
- [ ] `APP_DEBUG=false`
- [ ] Public `APP_URL` configured
- [ ] Redis configured for cache/queue
- [ ] Docker compose/template config validated
- [ ] Backend/frontend assets or images built
- [ ] CI passed
- [ ] OpenAPI contract tests passed
- [ ] Migrations reviewed
- [ ] Backup plan confirmed
- [ ] Queue workers supervised and restartable
- [ ] Reverb/WebSocket configuration verified
- [ ] Health checks pass
- [ ] API docs access verified
- [ ] Logs visible via container/runtime logging
- [ ] Rollback path documented for the release

## Related Docs

- `backend/docs/commands.md` (command cookbook)
- `backend/docs/docker.md` (Docker image/build context guidance)
- `backend/docs/release.md` (release workflow, SemVer, validation, rollback)
- `backend/docs/ci-cd.md` (CI checks and non-deploy workflow)
- `backend/docs/security.md` (security policies)
- `backend/docs/monitoring.md` (health/logging/observability)
- `backend/docs/realtime.md` (Reverb, channel auth, diagnostics, troubleshooting)
- `backend/docs/performance.md` (cache/query/queue performance)
