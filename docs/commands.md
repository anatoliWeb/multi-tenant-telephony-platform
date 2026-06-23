# commands.md — Development & Operations Guide

> This file contains common commands for working with the project in a consistent and efficient way.

---

## 🐳 Docker

### Build containers
```
docker-compose build
```

### Start all services
```
docker-compose up -d
```

### Stop all services
```
docker-compose down
```

### Restart services
```
docker-compose restart
```

### View logs
```
docker-compose logs -f
```

### View logs for specific service
```
docker-compose logs -f backend
docker-compose logs -f nginx
docker-compose logs -f frontend
```

---

## 🧠 Backend (Laravel)

### Enter backend container
```
docker exec -it multi_tenant_telephony_platform_backend bash
```

### Install dependencies
```
composer install
```

### Generate app key
```
php artisan key:generate
```

### Run migrations
```
php artisan migrate
```

### Clear cache
```
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Run queue worker (if used)
```
php artisan queue:work
```

---

## Frontend (Angular Dashboard)

### Enter frontend container
```
docker exec -it multi_tenant_telephony_platform_frontend sh
```

### Install dependencies
```
npm install
```

### Run dev server
```
npm run dev
```

### Build frontend
```
npm run build
```

---

## 🗄 Database (MySQL)

### Enter MySQL container
```
docker exec -it multi_tenant_telephony_platform_mysql mysql -u root -p
```

### Backup database
```
docker exec multi_tenant_telephony_platform_mysql mysqldump -u root -p saas > backup.sql
```

### Restore database
```
cat backup.sql | docker exec -i multi_tenant_telephony_platform_mysql mysql -u root -p saas
```

---

## ⚡ Redis

### Enter Redis CLI
```
docker exec -it multi_tenant_telephony_platform_redis redis-cli
```

---

## 🔐 Environment

### Copy environment file
```
cp .env.example .env
```

### Edit environment
```
nano .env
```

---

## 🧪 Useful Commands

### Check running containers
```
docker ps
```

### Remove all containers (cleanup)
```
docker-compose down -v
```

### Rebuild everything (clean start)
```
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

---

## 🚀 Development Flow

1. Start containers
2. Install backend dependencies
3. Run migrations
4. Start frontend
5. Implement features step-by-step from TODO.md

---

## 📌 Notes

- Always use environment variables from `.env`
- Avoid running commands on host machine — use containers
- Keep services isolated and reproducible
- Follow TODO.md for development steps

---

<!-- WHY:
Improves developer navigation and onboarding experience.
-->
## Related Documentation

- [Architecture](./architecture.md)
- [API](./api.md)
- [Commands](./commands.md)
- [Coding Standards](./coding-standards.md)
- [Main Docs](./README.md)
