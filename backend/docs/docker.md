# Docker

## Purpose

This project uses Docker-first local development and Docker-based production preparation.

This document explains the actual compose services, build strategy, environment files, volumes, ports, healthchecks, logs, security notes, and troubleshooting. It is not a provider-specific deployment runbook.

## Compose Files

- `docker-compose.yml`: local/dev stack with bind mounts, dev ports, MySQL/Redis persistence under `docker/data/*`, frontend dev servers, queue worker, Reverb, and optional Horizon profile.
- `docker-compose.prod.example.yml`: production template for deployment preparation. It is not the default deploy pipeline and should be adapted per environment.

Validate compose files:

```bash
docker compose config
docker compose -f docker-compose.prod.example.yml config
```

## Services

| Service | Purpose | Notes |
| --- | --- | --- |
| `backend` | Laravel/PHP-FPM application runtime | Uses `docker/php/Dockerfile`, mounts `./backend:/var/www` in local dev |
| `nginx` | HTTP entrypoint / reverse proxy | Uses `nginx:1.27-alpine` and `docker/nginx/default.conf` |
| `mysql` | MySQL 8 database | Local persistence under `./docker/data/mysql`; exposed on local port `3307` |
| `redis` | Redis cache/queue/realtime support | Local persistence under `./docker/data/redis`; exposed on local port `6379` |
| `queue-worker` | Laravel queue worker process | Reuses PHP image and runs `backend/docker/queue/entrypoint.sh` |
| `reverb` | Laravel Reverb websocket server | Reuses PHP image and runs `php artisan reverb:start` |
| `horizon` | Optional Laravel Horizon process | Uses compose profile `horizon` |
| `frontend` | Angular Dashboard dev container | Uses `docker/frontend/Dockerfile`, serves on local port `4200` |
| `vue-frontend` | Vue Admin Vite dev server | Uses `node:20-alpine`, serves on local port `5173` |

## Images and Build Strategy

### PHP/backend image

- Base image: `php:8.3-fpm`
- Installs PHP extensions and tools needed by Laravel, queues, Redis, and local development.
- Composer binary is copied from `composer:2`.
- APT cache is cleaned during build.
- `backend`, `queue-worker`, `reverb`, and `horizon` use the same PHP image strategy to avoid duplicated Dockerfiles.

### Frontend image

- Angular dev container uses `node:20-alpine`.
- Vue dev container uses `node:20-alpine` directly from compose.

### Build context and cache

- Root build context is used by current compose services.
- `.dockerignore` keeps the build context lean and prevents local/runtime artifacts from entering images.
- Dockerfiles must not copy `.env` into image layers.
- Local dev uses bind mounts and runtime dependency checks for convenience.

## Environment Files

Environment sources:

- local/dev: `.env` at repository root via compose `env_file`
- backend examples: `backend/.env.example`
- production template: `backend/.env.production.example`

Policy:

- secrets are injected via environment at runtime
- real secrets are never committed
- `.env` files are excluded from build context
- production values should be provided by deployment-specific secret management or host-level env injection

## Volumes and Persistence

Local volumes and bind mounts:

- `./backend:/var/www` for backend source
- `./frontend:/app` for Angular source
- `frontend_node_modules:/app/node_modules`
- `vue_node_modules:/var/www/node_modules`
- `./docker/data/mysql:/var/lib/mysql`
- `./docker/data/redis:/data`
- `./docker/supervisor/supervisord.conf:/etc/supervisor/supervisord.conf`

Production guidance:

- avoid bind mounting application source in production runtime images
- keep database/cache data on managed volumes or managed services
- ensure Laravel writable paths (`storage`, `bootstrap/cache`) are explicitly writable

## Ports and Networking

Local published ports:

- Nginx/backend: `${APP_PORT}:80` (commonly `8080`)
- Angular Dashboard: `${FRONT_PORT}:4200`
- Vue Admin Vite: `5173:5173`
- Reverb: `6001:6001`
- MySQL: `3307:3306`
- Redis: `6379:6379`

Internal service communication uses compose service names such as `backend`, `mysql`, `redis`, and `reverb`.

Production guidance:

- expose only the public HTTP/HTTPS ingress and websocket ingress as needed
- do not publish DB/Redis ports publicly
- put DB/Redis behind private networking or managed services

## Healthchecks

Current local healthchecks:

- `backend`: `php -v`
- `frontend`: HTTP probe against `127.0.0.1:4200`
- `mysql`: `mysqladmin ping`
- `redis`: `redis-cli ... ping`

Application-level health:

- `/health`: public liveness endpoint
- `/api/v1/system/health`: protected readiness/status endpoint

Container healthchecks are lightweight process/dependency checks. Application readiness should be verified through the health endpoints documented in `backend/docs/monitoring.md`.

## Logs

Compose uses JSON-file log rotation:

- `driver: json-file`
- `max-size: 10m`
- `max-file: 3` in local compose
- `max-file: 5` in production example compose

Nginx logs:

- `access_log /dev/stdout`
- `error_log /dev/stderr warn`

Laravel/container logging:

- local defaults may use Laravel stack/single for developer convenience
- container-ready production recommendation is stderr/stack logging
- queue workers emit logs through Laravel logging and supervisor stdout/stderr

Useful commands:

```bash
docker compose logs backend --tail=100
docker compose logs queue-worker --tail=100
docker compose logs nginx --tail=100
docker compose logs reverb --tail=100
docker compose logs --since=10m
```

## Security Notes

- `.dockerignore` excludes `.env`, `.env.*`, dependency folders, logs, cache, local data volumes, and build artifacts.
- Dockerfiles must not copy `.env` into images.
- Nginx denies hidden files such as `.env` and `.git`.
- Directory listing is disabled in Nginx (`autoindex off`).
- Local containers may run default/root users for bind mount compatibility.
- Production should prefer non-root runtime users and least-privilege writable paths after permissions are validated.
- DB/Redis ports are exposed for local convenience only and should not be public in production.
- Never log or echo passwords, tokens, authorization headers, or app keys in entrypoints/scripts.

## Common Commands

```bash
docker compose up -d
docker compose ps
docker compose config
docker compose build backend
docker compose build frontend
docker compose build nginx
docker compose logs backend --tail=100
docker compose down
```

More commands are centralized in `backend/docs/commands.md`.

## Troubleshooting

### Docker daemon unavailable / pipe permission denied

- Confirm Docker Desktop/daemon is running.
- Re-run `docker compose ps`.
- Re-run `docker compose config` to separate config errors from daemon availability.

### Containers are up but unhealthy

- Inspect `docker compose ps`.
- Check service-specific logs with `docker compose logs <service> --tail=100`.
- For backend, verify PHP container health and Laravel config cache.

### Backend permission or mount issues

- Confirm `./backend` is mounted to `/var/www`.
- Clear Laravel caches after env/config changes.
- Verify writable `storage` and `bootstrap/cache` paths.

### Redis/MySQL connection errors

- Confirm containers are up and healthy.
- Confirm Laravel env uses compose service names (`mysql`, `redis`) from inside containers.
- Check `docker compose logs mysql --tail=100` and `docker compose logs redis --tail=100`.

### Nginx issues

```bash
docker compose exec nginx nginx -t
docker compose logs nginx --tail=100
```

Confirm Nginx can reach `backend:9000`.

### Queue worker not processing

- Check `docker compose logs queue-worker --tail=100`.
- Confirm `QUEUE_CONNECTION=redis`.
- Run `docker compose exec backend php artisan queue:failed`.
- Restart workers with `docker compose exec backend php artisan queue:restart`.

### Frontend container/build issues

- Angular: `docker compose exec frontend npm run build`
- Vue Admin: `docker compose exec backend npm run build`
- If dependencies look stale, re-run `npm ci` in the owning container.

## Related Docs

- `backend/docs/commands.md`
- `backend/docs/deployment.md`
- `backend/docs/security.md`
- `backend/docs/monitoring.md`
- `backend/docs/performance.md`
- `backend/docs/realtime.md`
