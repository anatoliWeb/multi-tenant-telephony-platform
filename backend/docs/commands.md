# Commands

Central command cookbook for daily development, testing, Docker operations, API docs, queues, monitoring, and release checks.

Run commands from the repository root unless a section says otherwise.

## Safety Notes

- Do not paste real secrets, tokens, credentials, or production values into commands.
- Do not run destructive production commands without a reviewed backup/rollback plan.
- Do not run multiple backend test processes in parallel against the same `saas_testing` database.
- `docker-compose.prod.example.yml` is a template only, not a production deployment manifest.

## Docker

Start and inspect the local stack:

```bash
docker compose up -d
docker compose ps
docker compose config
```

Logs:

```bash
docker compose logs backend --tail=100
docker compose logs queue-worker --tail=100
docker compose logs nginx --tail=100
docker compose logs redis --tail=100
docker compose logs --since=10m
```

Build and lifecycle:

```bash
docker compose build backend
docker compose build frontend
docker compose build nginx
docker compose down
```

Production example validation:

```bash
docker compose -f docker-compose.prod.example.yml config
```

## Backend / Laravel

Install and prepare the backend:

```bash
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
docker compose exec backend php artisan migrate --seed
```

Seeder-specific commands:

```bash
docker compose exec backend php artisan app:seed-core
docker compose exec backend php artisan app:seed-demo
docker compose exec backend php artisan app:seed-performance --tenants=3 --users=150
```

Seeder notes:

- `app:seed-core` is the mandatory baseline and is safe to rerun.
- `app:seed-demo` is local/demo only and refuses production.
- `app:seed-performance` is explicit and should be used only when high-volume fixtures are needed.
- Use `--allow-production` only for controlled performance validation.
- `migrate:fresh --seed` now routes through `DatabaseSeeder`, so local runs get the demo baseline and testing runs get deterministic fixtures automatically.
- The generic `migrate --seed` flow should be reserved for environments that intentionally want the full default database seed path.

Clear framework caches:

```bash
docker compose exec backend php artisan optimize:clear
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan cache:clear
```

Production-style migration command:

```bash
docker compose exec backend php artisan migrate --force
```

## Backend Tests

Full backend test suite:

```bash
docker compose exec backend php -d memory_limit=512M artisan test
```

Targeted filters:

```bash
docker compose exec backend php -d memory_limit=512M artisan test --filter=Api --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=Chat --stop-on-failure
docker compose exec backend php -d memory_limit=512M artisan test --filter=Auth --stop-on-failure
```

Composer test scripts:

```bash
docker compose exec backend composer test:openapi
docker compose exec backend composer test:chat
docker compose exec backend composer test:api
docker compose exec backend composer test:auth
docker compose up -d scheduler
docker compose exec backend php artisan schedule:work
```

Preflight/debug:

```bash
docker compose exec backend composer test:preflight
```

Important: run backend tests sequentially unless separate parallel test databases are configured.

## Vue Admin

Vue Admin lives inside the `backend` workspace.

```bash
docker compose exec backend npm ci
docker compose exec backend npm test
docker compose exec backend npm run build
```

Development server through the dedicated Vue container:

```bash
docker compose exec vue-frontend npm run dev -- --host 0.0.0.0 --port 5173
```

## Angular Dashboard

Angular Dashboard lives in the `frontend` workspace.

```bash
docker compose exec frontend npm ci
docker compose exec frontend npm test -- --watch=false
docker compose exec frontend npm run build
```

Development server:

```bash
docker compose exec frontend npm run start
```

## OpenAPI / Swagger

Useful local URLs:

- Docs portal: `http://localhost:8080/docs/api/portal`
- Filtered OpenAPI spec: `http://localhost:8080/docs/api.filtered.json`
- Raw Swagger UI for full-access docs users: `http://localhost:8080/docs/api`
- Raw OpenAPI JSON for full-access docs users: `http://localhost:8080/docs/api.json`

OpenAPI contract suite:

```bash
docker compose exec backend composer test:openapi
```

Strict local access verification:

```bash
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan cache:clear
```

Then verify docs access with `API_DOCS_LOCAL_BYPASS=false` in the local environment.

## Queue Workers

Queue worker operations:

```bash
docker compose exec backend php artisan queue:restart
docker compose exec backend php artisan queue:failed
docker compose exec backend php artisan queue:retry all
docker compose exec backend php artisan queue:flush
```

Queue status command:

```bash
docker compose exec backend php artisan system:queue-status
```

Inspect worker logs:

```bash
docker compose logs queue-worker --tail=100
```

## Monitoring / Logs

Health URLs:

- Public liveness: `http://localhost:8080/health`
- Protected readiness: `http://localhost:8080/api/v1/system/health`

Container logs:

```bash
docker compose logs backend --tail=100
docker compose logs queue-worker --tail=100
docker compose logs nginx --tail=100
docker compose logs reverb --tail=100
docker compose logs --since=10m
```

Nginx config check:

```bash
docker compose exec nginx nginx -t
```

Realtime diagnostics and troubleshooting are documented in `backend/docs/realtime.md`.

## Cache / Redis

Redis smoke check:

```bash
docker compose exec redis redis-cli ping
```

Laravel cache commands:

```bash
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan optimize:clear
```

Redis is the recommended cache/queue backend for production-like environments. Avoid caching user-specific responses globally.

## Release / Deployment Checks

Local release validation baseline:

```bash
docker compose config
docker compose -f docker-compose.prod.example.yml config
docker compose exec backend composer test:openapi
docker compose exec backend php -d memory_limit=512M artisan test --filter=Api --stop-on-failure
docker compose exec backend npm test
docker compose exec backend npm run build
docker compose exec frontend npm test -- --watch=false
docker compose exec frontend npm run build
```

Release tag example:

```bash
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin v0.1.0
```

See `backend/docs/release.md` and `backend/docs/deployment.md` before using release commands.

## Troubleshooting

Config/cache drift:

```bash
docker compose exec backend php artisan optimize:clear
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan cache:clear
```

Docker daemon unavailable:

```bash
docker compose ps
docker compose config
```

Testing DB lifecycle race:

- Stop any parallel backend test runs.
- Re-run the failed filter once sequentially.
- If the project provides a testing DB reset command for the current environment, run it before retrying.

Mount or root-file visibility issues:

- Backend container mounts `./backend` as `/var/www`.
- Root-level files such as `README.md` may be unavailable from backend-only tests and should use graceful skips.

Frontend build warnings:

- Re-run the relevant build in the owning workspace.
- For Vue Admin use `docker compose exec backend npm run build`.
- For Angular Dashboard use `docker compose exec frontend npm run build`.
