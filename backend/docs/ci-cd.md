# CI/CD Preparation

## Scope

This project currently provides CI foundation only. It does not perform automatic production deployment.

## Workflows

Primary workflow: `.github/workflows/ci.yml`

Included jobs:

- `backend-tests`
- `backend-frontend`
- `angular-frontend`
- `docker-config`

## Required services

`backend-tests` uses ephemeral service containers:

- MySQL 8.0
- Redis 7

Testing env stays isolated from development DB.

## Environment strategy

CI backend tests rely on `backend/.env.testing` plus explicit workflow overrides for:

- `APP_ENV=testing`
- MySQL host/port/database credentials
- `CACHE_STORE=array`
- `SESSION_DRIVER=array`
- `QUEUE_CONNECTION=sync`
- `BROADCAST_CONNECTION=null`

This keeps CI deterministic without requiring full websocket/queue runtime.

## What CI checks

- Backend API test smoke (`--filter=Api`)
- OpenAPI contract suite (`composer test:openapi`)
- Vue Admin tests + build (inside `backend`)
- Angular dashboard tests + build (inside `frontend`)
- Docker compose config validation for:
  - `docker-compose.yml`
  - `docker-compose.prod.example.yml`

## What CI does not do

- No production deployment
- No secret provisioning
- No production migrations against live environments
- No container image publish/release pipeline in this phase

## Secrets policy

- Never hardcode real tokens/passwords in workflows.
- Use GitHub encrypted secrets only when deployment/release workflows are introduced.
- Keep sample values as placeholders/testing-only values.

## Future deployment path

Next step can add separate release workflows:

1. Build and scan images
2. Push to registry
3. Deploy via environment-specific pipeline
4. Run post-deploy health checks

## Related Documents

- `backend/docs/deployment.md` (production configuration baseline)
- `backend/docs/release.md` (release checklist, SemVer/tag strategy, rollback notes)
- `backend/docs/architecture.md` (internal module contracts and dependency boundaries)
