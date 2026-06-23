# Release Workflow

## Release Workflow

This project uses a release-preparation workflow, not automatic production deployment.

Release flow:

1. Ensure `main` branch is green in CI.
2. Prepare changelog/release notes.
3. Run validation commands locally (or in release-check workflow in the future).
4. Create an annotated SemVer tag.
5. Publish GitHub release notes.
6. Execute deployment using environment-specific process (outside this repository scope).

## Versioning Strategy

Semantic Versioning is used:

- `vMAJOR.MINOR.PATCH`
- Example: `v0.1.0`

Rules:

- `MAJOR`: breaking API/contract changes
- `MINOR`: backward-compatible features
- `PATCH`: backward-compatible fixes

Tagging example:

```bash
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin v0.1.0
```

## Pre-release Checklist

- CI pipeline (`.github/workflows/ci.yml`) is green
- `APP_DEBUG=false` policy confirmed in production template
- OpenAPI contract tests pass
- Backend API smoke suite passes
- Vue Admin tests/build pass
- Angular Dashboard tests/build pass
- Docker compose config validation passes
- Migration plan reviewed (`php artisan migrate --force` for release)
- Queue/realtime critical smoke checks reviewed
- Release notes/changelog updated

## Validation Commands

```bash
docker compose config
docker compose -f docker-compose.prod.example.yml config

cd backend
composer test:openapi
php -d memory_limit=512M artisan test --filter=Api --stop-on-failure
npm test
npm run build

cd ../frontend
npm test -- --watch=false
npm run build
```

## Build Artifacts

Current foundation validates builds; it does not publish artifacts automatically.

Expected release artifacts (future step):

- Backend Docker image(s)
- Frontend build artifacts
- Release notes/changelog bundle

## Docker Image Strategy

- Dev compose is not production deployment.
- Production baseline is documented in:
  - `backend/.env.production.example`
  - `docker-compose.prod.example.yml`
- Image publish/push strategy is intentionally out of scope in this phase.

## Database Migrations

- Migrations are executed explicitly during release:
  - `php artisan migrate --force`
- Run schema-impact review before tagging release.
- Avoid unreviewed destructive schema changes in release window.

## Rollback Strategy

Application rollback baseline:

1. Roll back application version to previous known-good tag/image.
2. Restart app/queue workers.
3. Run health checks.

Database rollback note:

- Prefer forward-fix migration for production incidents when possible.
- If rollback is required and safe, use reviewed rollback migration steps only.

## Post-release Verification

- `GET /health` returns `status=ok`
- Protected monitoring endpoint reports healthy/degraded state safely
- Core auth flow works (`/api/v1/auth/session/*`)
- OpenAPI routes remain available with access policy:
  - `/docs/api/portal`
  - `/docs/api.filtered.json`
  - raw docs only for full-access users
- Queue workers process jobs normally
- Realtime connectivity basic smoke check passes

## Known Non-goals

- No automatic production deployment in GitHub Actions
- No registry push workflow in this phase
- No secret manager integration workflow in this phase
- No Kubernetes/Terraform rollout logic in this phase

## Related Documents

- `backend/docs/architecture.md` (internal module boundaries and contract-first dependencies)
