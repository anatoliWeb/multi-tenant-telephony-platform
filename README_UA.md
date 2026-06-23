# Multi-Tenant Telephony Platform

[English](README.md) | [Українська](README_UA.md)

`multi-tenant-telephony-platform` — це portfolio-grade основа SaaS-платформи для побудови багатокористувацького керування телефонією, realtime-комунікацій, браузерних дзвінків і конференцій.

Репозиторій уже містить зрілу базу застосунку: Laravel API, Angular-кабінет tenant-користувачів, Vue-панель адміністрування платформи, RBAC, realtime-чат, OpenAPI-документацію, Docker, черги, monitoring та автоматизовані тести.

> Телефонія, multi-tenancy, інтеграція з FreeSWITCH, браузерна звонилка, конференц-кімнати та billing додаються поступово. Їх не слід вважати повністю реалізованими, доки вони явно не позначені як завершені.

## Поточна основа

- API-first Laravel backend у `/api/v1`
- Angular-застосунок для tenant-користувачів
- Vue-панель адміністрування платформи
- Власна RBAC-система з перевіркою permissions на backend
- Permission-aware навігація в Angular і Vue
- Realtime-чат із direct і group conversations
- Повідомлення, учасники, typing, presence, вкладення, read states, webhooks та external API
- Laravel Reverb і Laravel Echo
- OpenAPI-документація через Scramble
- Redis cache і queue foundation
- Queue worker та опційна підтримка Horizon
- Notifications та activity logs
- Health checks, structured logging і monitoring foundation
- Docker-середовище для локальної розробки
- Backend, Angular і Vue тести
- Основа modular monolith із задокументованою стратегією майбутнього виділення сервісів

## Заплановані можливості платформи

Поточний застосунок буде розширюватися без створення дубльованого проєкту:

- Shared-database multi-tenancy
- Tenant memberships і перемикання tenant
- Розділення platform і tenant ролей
- Tenant isolation для чату, realtime-каналів, черг, cache, storage та audit logs
- Корпоративні й особисті контакти
- Extensions і телефонні номери
- Call logs і телефонна статистика
- Ring groups, queues та IVR
- Fake PBX adapter для розробки й тестів
- Інтеграція з FreeSWITCH через contracts та adapters
- Browser softphone на SIP.js
- Кнопка дзвінка у direct і group chat
- Ad-hoc і постійні conference rooms
- Перетворення активного дзвінка на конференцію
- Запрошення користувачів, extensions, контактів і зовнішніх номерів
- Захищений transport для аудіо
- Записи дзвінків і конференцій
- Webhooks, usage accounting, billing foundation і reports
- Telephony monitoring і демонстраційні набори даних

## Технологічний стек

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

- Angular 21 для tenant-застосунку
- Vue 3, Pinia, Vue Router і Vite для platform administration
- SCSS

### Infrastructure та DevOps

- Docker Compose
- Nginx
- Queue worker
- Опційний Horizon profile
- GitHub Actions CI foundation

## Відповідальність застосунків

### Laravel Backend

Laravel є єдиним backend API та головним джерелом authorization.

Він відповідає за:

- authentication;
- roles і permissions;
- tenant isolation;
- chat і realtime authorization;
- telephony management;
- call і conference control;
- integrations;
- queues та events;
- notifications і activity logs;
- webhooks;
- billing і reports;
- monitoring.

### Angular Tenant Application

Angular є основним користувацьким застосунком.

Він призначений для:

- tenant dashboard;
- чату;
- контактів;
- browser softphone;
- дзвінків і call history;
- conference rooms;
- extensions і phone numbers;
- queues та IVR;
- tenant reports і billing views;
- налаштувань користувача.

### Vue Platform Administration

Vue використовується для адміністрування всієї платформи.

Він призначений для:

- керування tenants;
- platform users;
- глобального permission catalog;
- захищених system roles;
- activity logs і support tools;
- monitoring черг і realtime;
- FreeSWITCH та integration health;
- platform-level statistics і billing administration.

## Архітектура

Поточна архітектура — modular monolith з API-first межами, service layer, events, jobs, policies і задокументованими варіантами подальшого виділення сервісів.

Наявний застосунок розширюється без перенесення в новий Laravel-проєкт. Готові backend, frontend, chat, RBAC, realtime, notification, queue, test і documentation foundations зберігаються та адаптуються.

- [Архітектура backend](backend/docs/architecture.md)
- [Стратегія майбутнього виділення сервісів](backend/docs/microservices.md)
- [Індекс документації](docs/README.md)

## Authentication та RBAC

Поточна основа містить:

- session authentication;
- bearer і token support;
- Laravel Sanctum;
- roles і permissions;
- permission middleware;
- permission cache;
- backend policies;
- Angular permission guards;
- Vue permission guards;
- permission-aware navigation та actions.

Цільові правила RBAC:

- role є набором permissions;
- user може мати декілька roles;
- backend є остаточним джерелом authorization;
- недоступний функціонал приховується на frontend;
- tenants можуть створювати custom roles із системного permission catalog;
- platform і tenant permissions будуть розділені;
- direct user permissions не входять до цільової моделі першого release.

## Chat та Realtime

Поточний чат уже підтримує:

- direct conversations;
- group conversations;
- participants і participant roles;
- text messages;
- редагування та видалення повідомлень;
- attachments;
- read і delivery states;
- typing indicators;
- presence;
- webhooks;
- external API;
- realtime events через Reverb.

Запланована інтеграція чату з телефонією:

- кнопка аудіодзвінка в direct chat;
- створення group call із group chat;
- call і missed-call event messages;
- conference invitation messages;
- conference room chat;
- recording links із permission checks.

## API Documentation

- Permission-aware documentation portal: `/docs/api/portal`
- OpenAPI spec, відфільтрований для користувача: `/docs/api.filtered.json`
- Swagger UI для користувачів із повним доступом: `/docs/api`
- Raw OpenAPI specification: `/docs/api.json`

## Security Foundation

- Rate limiting
- Secure headers
- Validation hardening
- Token security
- Backend authorization
- Realtime channel authorization
- Приватний доступ до attachments
- Docker security review foundation
- Structured logs і правила для sensitive data

Запланована VoIP-безпека:

- HTTPS
- WSS
- TLS
- DTLS-SRTP
- зашифровані SIP і PBX credentials
- private recording storage
- signed recording URLs
- майбутній режим внутрішніх E2EE-дзвінків

## Локальний запуск

Працюйте з кореневої директорії репозиторію.

### 1. Підготуйте environment configuration

Linux/macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 2. Запустіть наявний Docker stack

```bash
docker compose up -d
```

### 3. Встановіть backend dependencies

```bash
docker compose exec backend composer install
```

### 4. Згенеруйте Laravel application key

Запускайте команду лише тоді, коли `APP_KEY` ще не налаштовано:

```bash
docker compose exec backend php artisan key:generate
```

### 5. Запустіть migrations і seeders

```bash
docker compose exec backend php artisan migrate --seed
```

Не використовуйте `migrate:fresh` у середовищі, де є дані, які потрібно зберегти.

### 6. Зберіть Vue administration frontend

```bash
docker compose exec backend npm ci
docker compose exec backend npm run build
```

### 7. Зберіть Angular tenant frontend

```bash
docker compose exec frontend npm ci
docker compose exec frontend npm run build
```

## Корисні адреси

Стандартні значення з `docker-compose.yml` і `.env`:

- Laravel через Nginx: `http://localhost:8080`
- API base: `http://localhost:8080/api/v1`
- Vue administration dev server: `http://localhost:5173`
- Angular tenant dashboard: `http://localhost:4200`
- API documentation portal: `http://localhost:8080/docs/api/portal`
- Swagger UI для повного доступу: `http://localhost:8080/docs/api`
- Public liveness endpoint: `http://localhost:8080/health`

Порти можуть відрізнятися, якщо локальні environment values були змінені.

## Тестування

### Backend

```bash
docker compose exec backend php artisan test
docker compose exec backend composer test:openapi
```

### Vue administration frontend

Використовуйте scripts із `backend/package.json`:

```bash
docker compose exec backend npm test
docker compose exec backend npm run build
```

### Angular tenant frontend

Використовуйте scripts із `frontend/package.json`:

```bash
docker compose exec frontend npm test -- --watch=false
docker compose exec frontend npm run build
```

> Не запускайте паралельно декілька backend test processes проти однієї бази `saas_testing`.

## Карта документації

| Тема | Документ |
| --- | --- |
| Індекс документації | [docs/README.md](docs/README.md) |
| Архітектура | [backend/docs/architecture.md](backend/docs/architecture.md) |
| Підготовка OpenAPI | [backend/docs/api/openapi-preparation.md](backend/docs/api/openapi-preparation.md) |
| Генератор OpenAPI | [backend/docs/api/openapi-generator.md](backend/docs/api/openapi-generator.md) |
| Security | [backend/docs/security.md](backend/docs/security.md) |
| Performance | [backend/docs/performance.md](backend/docs/performance.md) |
| Monitoring | [backend/docs/monitoring.md](backend/docs/monitoring.md) |
| Commands | [backend/docs/commands.md](backend/docs/commands.md) |
| Realtime | [backend/docs/realtime.md](backend/docs/realtime.md) |
| Docker | [backend/docs/docker.md](backend/docs/docker.md) |
| Deployment | [backend/docs/deployment.md](backend/docs/deployment.md) |
| CI/CD | [backend/docs/ci-cd.md](backend/docs/ci-cd.md) |
| Release process | [backend/docs/release.md](backend/docs/release.md) |
| Майбутнє виділення сервісів | [backend/docs/microservices.md](backend/docs/microservices.md) |

## Production Notes

- Використовуйте `backend/.env.production.example` як основу
- Установіть `APP_DEBUG=false`
- Використовуйте Redis для cache та queues
- Використовуйте HTTPS, secure cookies і HSTS
- Не відкривайте MySQL і Redis у публічну мережу
- Запускайте migrations свідомо під час release
- Зберігайте secrets поза репозиторієм
- Захищайте private attachments і майбутні recordings
- Перегляньте [backend/docs/deployment.md](backend/docs/deployment.md)

## Статус проєкту

Цей репозиторій є активною основою Multi-Tenant Telephony Platform.

Наразі реалізовано:

- Laravel API foundation;
- Angular tenant dashboard foundation;
- Vue platform administration foundation;
- authentication та RBAC;
- chat і realtime;
- notifications та activity logs;
- OpenAPI documentation;
- Docker, queues, monitoring і testing foundations.

Поступово додаються:

- multi-tenancy;
- telephony management;
- FreeSWITCH integration;
- browser softphone;
- calls from chat;
- conference rooms;
- recordings;
- telephony billing і reports.

Проєкт не заявляє повну production-ready сумісність із кожним можливим середовищем розгортання. Поточна реалізація залишається modular monolith, а майбутнє виділення сервісів розглядається як архітектурна можливість.
