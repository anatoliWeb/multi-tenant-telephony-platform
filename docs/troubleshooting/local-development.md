# Local Development Troubleshooting

Цей документ призначений для швидких нотаток про типові локальні проблеми в `multi-tenant-telephony-platform`: Docker, Laravel, Vue Admin, Angular, FreeSWITCH, база даних, Vite, CSP, локальні сертифікати та інші дрібні граблі, які можуть повторюватися.

Рекомендований шлях у репозиторії:

```text
 docs/troubleshooting/local-development.md
```

---

## Vue Admin: біла сторінка через CSP і Vite dev server

### Симптоми

Відкриваємо Vue Admin login:

```text
http://localhost:8080/admin/login
```

Сторінка завантажується, але залишається білою.

У Chrome DevTools → Network видно:

```text
login        200
@vite/client blocked:csp
main.ts      blocked:csp
```

Або може показуватися коротше:

```text
client  blocked:csp
main.ts blocked:csp
```

У відповіді `curl` для сторінки видно CSP header без дозволу для Vite dev server:

```powershell
curl.exe -I http://localhost:8080/admin/login
```

Проблемний приклад:

```text
Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:*;
```

Головна ознака проблеми:

```text
script-src 'self' 'unsafe-inline' 'unsafe-eval';
```

У `script-src` немає локальних Vite origins:

```text
http://localhost:*
http://127.0.0.1:*
```

---

### Причина

Vue Admin login HTML підключає Vite dev scripts приблизно так:

```text
http://localhost:5173/@vite/client
http://localhost:5173/resources/js/main.ts
```

Але Laravel security/CSP header дозволяв scripts тільки з `'self'`.

Через це браузер блокував `@vite/client` і `main.ts` ще до завантаження. Тому HTML сторінки приходив із кодом `200`, але Vue Admin не стартував, і сторінка виглядала порожньою.

Важливо: це не проблема бази даних, не проблема seeders і не проблема авторизації. Це саме блокування JavaScript через `Content-Security-Policy`.

---

### Виправлення

Файл:

```text
backend/config/security.php
```

CSP має бути environment-aware.

Для `local` і `testing` потрібно дозволити browser-reachable Vite dev origins.

Очікуваний local/testing варіант для `script-src`:

```text
script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* http://127.0.0.1:*;
```

Очікуваний local/testing варіант для `connect-src`:

```text
connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:* wss://localhost:* wss://127.0.0.1:*;
```

Для production не треба додавати localhost exceptions.

Не можна виправляти це так:

```text
script-src *
connect-src *
```

І не треба повністю вимикати CSP.

---

### Як перевірити після виправлення

Очистити кеш Laravel:

```powershell
docker compose exec -T backend php artisan optimize:clear
```

Перезапустити контейнери, які віддають backend/nginx/Vue Admin:

```powershell
docker compose restart backend nginx vue-frontend
```

Перевірити CSP header:

```powershell
curl.exe -I http://localhost:8080/admin/login
```

У відповіді має бути `script-src`, який містить:

```text
http://localhost:* http://127.0.0.1:*
```

Перевірити Vite client:

```powershell
curl.exe http://localhost:5173/@vite/client
```

Очікувано: повертається JavaScript код Vite client.

Після цього відкрити:

```text
http://localhost:8080/admin/login
```

У DevTools → Network очікувано:

```text
login         200
@vite/client  200
main.ts       200
```

Або мінімум:

```text
@vite/client  більше не blocked:csp
main.ts       більше не blocked:csp
```

---

### Важливий нюанс із `curl -I`

У цьому проєкті може бути така ситуація:

```powershell
curl.exe -I http://localhost:5173/@vite/client
```

повертає:

```text
404 Not Found
```

Але звичайний GET працює:

```powershell
curl.exe http://localhost:5173/@vite/client
```

і повертає Vite client JavaScript.

Це може бути особливість HEAD-запиту в цьому dev setup. Для браузера важливий саме GET, тому `curl.exe http://localhost:5173/@vite/client` є кориснішою перевіркою.

---

### Тести, які підтвердили виправлення

Після виправлення було додано regression test у:

```text
backend/tests/Feature/Api/SecurityHeadersTest.php
```

Перевірка:

```powershell
docker compose exec -T backend php artisan test --env=testing tests/Feature/Api/SecurityHeadersTest.php --stop-on-failure
```

Очікувано:

```text
PASS
```

Також перевірити вручну:

```powershell
curl.exe -I http://localhost:8080/admin/login
curl.exe http://localhost:5173/@vite/client
```

---

### Короткий чеклист

Якщо Vue Admin знову білий:

1. Відкрити DevTools → Network.
2. Перевірити, чи `@vite/client` або `main.ts` мають `blocked:csp`.
3. Виконати:

   ```powershell
   curl.exe -I http://localhost:8080/admin/login
   ```

4. Перевірити, чи `script-src` містить:

   ```text
   http://localhost:* http://127.0.0.1:*
   ```

5. Якщо не містить, перевірити `backend/config/security.php`.
6. Очистити кеш:

   ```powershell
   docker compose exec -T backend php artisan optimize:clear
   ```

7. Перезапустити:

   ```powershell
   docker compose restart backend nginx vue-frontend
   ```

---

## MySQL schema dump: `migrate:fresh --seed` падає через self-signed SSL

### Симптоми

Команда:

```powershell
docker compose exec -T backend php artisan migrate:fresh --seed
```

падає на завантаженні schema dump:

```text
database/schema/mysql-schema.sql FAIL
```

Помилка:

```text
ERROR 2026 (HY000): TLS/SSL error: self-signed certificate in certificate chain
```

---

### Причина

Laravel schema-dump loader використовує системний `mysql` client, а не звичайне Laravel PDO-підключення.

MySQL CLI перевіряв SSL-сертифікат і відхиляв self-signed certificate chain у локальному Docker-середовищі.

---

### Виправлення

У local/testing середовищі має бути вимкнена перевірка server certificate для MySQL schema loading:

```env
MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false
```

Це має бути задокументовано у:

```text
.env.example
backend/.env.example
```

У `backend/config/database.php` local/testing default має враховувати цей прапорець.

---

### Перевірка

```powershell
docker compose exec -T backend php artisan optimize:clear
docker compose exec -T backend php artisan migrate:fresh --seed
docker compose exec -T backend php artisan migrate:fresh --seed --env=testing
```

Очікувано: schema dump завантажується, seeders проходять.

---

## FreeSWITCH local notes

### Стабільна назва контейнера

Для локального FreeSWITCH використовується стабільна назва контейнера:

```text
multi-tenant-telephony-platform-freeswitch
```

Це зроблено, щоб локальні скрипти й документація не залежали від Compose-generated імені на зразок:

```text
multi-tenant-telephony-platform-freeswitch-1
```

FreeSWITCH лишається optional profile і не входить у дефолтний запуск.

---

### Перевірка FreeSWITCH

```powershell
docker compose --profile freeswitch up -d freeswitch
docker compose ps
docker compose exec -T freeswitch fs_cli -x "status"
docker compose exec -T freeswitch fs_cli -x "sofia status profile internal"
docker compose exec -T freeswitch fs_cli -x "global_getvar local_ip_v4"
```

Після provisioning demo users:

```powershell
docker compose exec -T freeswitch fs_cli -x "user_exists id 1001 <runtime-domain>"
docker compose exec -T freeswitch fs_cli -x "user_exists id 1002 <runtime-domain>"
```

`<runtime-domain>` зазвичай береться з:

```powershell
docker compose exec -T freeswitch fs_cli -x "global_getvar local_ip_v4"
```

Наприклад:

```text
172.18.0.12
```

---

### Browser SIP domain і FreeSWITCH runtime domain не одне й те саме

Для браузера SIP profile має використовувати browser-reachable значення:

```text
localhost
wss://localhost:7443
```

А FreeSWITCH всередині Docker може шукати users через runtime directory domain:

```text
172.18.0.12
```

Не треба підставляти Docker runtime IP у browser-facing SIP URI.

Правильно:

```text
sip:1001@localhost
sip:1002@localhost
```

А не:

```text
sip:1001@172.18.0.12
```

---

## Корисні базові команди

Очистити Laravel cache:

```powershell
docker compose exec -T backend php artisan optimize:clear
```

Перезапустити backend/nginx/Vue Admin:

```powershell
docker compose restart backend nginx vue-frontend
```

Перезапустити все:

```powershell
docker compose restart
```

Повністю пересидити локальну БД:

```powershell
docker compose exec -T backend php artisan migrate:fresh --seed
```

Пересидити testing DB:

```powershell
docker compose exec -T backend php artisan migrate:fresh --seed --env=testing
```

Запустити focused backend tests:

```powershell
docker compose exec -T backend php artisan test --env=testing --filter=CallControl
docker compose exec -T backend php artisan test --env=testing --filter=FreeSwitch
```

## Seed baseline checklist

If local demo users or testing fixtures are missing after a reset, run:

```powershell
docker compose exec -T backend php artisan migrate:fresh --seed
docker compose exec -T backend php artisan migrate:fresh --seed --env=testing
```

Expected local demo users include `platform-admin@test.local` and `platform-support@test.local`.
Expected testing fixtures include `test-platform-admin@test.local`, `test-tenant-owner@test.local`, `test-tenant-admin@test.local`, and `test-tenant-agent@test.local`.

Seed-only orchestration belongs in `backend/database/seeders`, not in `App\Services`.

## Local SIP transport: WebSocket close `1006`

If the Angular softphone shows:

```text
WebSocket closed ... code: 1006
```

the browser usually could not complete the local FreeSWITCH transport handshake.

Check these local-only values:

- `ws://localhost:5066` for the local demo fallback;
- `wss://localhost:7443` for the trusted-TLS path;
- `docker-compose.yml` publishes both `5066/tcp` and `7443/tcp` on the optional FreeSWITCH profile.

Recommended checks:

```powershell
docker compose --profile freeswitch up -d freeswitch
docker compose exec -T freeswitch fs_cli -x "sofia status profile internal"
docker compose exec -T freeswitch fs_cli -x "show registrations"
```

If the browser still rejects WSS, use the local demo fallback only for development and keep production on trusted WSS.

## FreeSWITCH browser auth domain mismatch

If the browser sends:

```text
REGISTER sip:localhost SIP/2.0
401 Unauthorized
REGISTER ... realm="localhost"
403 Forbidden
```

then FreeSWITCH is still resolving the browser-facing SIP domain differently
from the browser profile.

In this repository the fix is a local `localhost` directory alias that is
copied into the running FreeSWITCH container during demo provisioning. That
keeps the browser SIP domain as `localhost` while still preserving the runtime
domain checks for Docker diagnostics.

Verify with:

```powershell
./docker/freeswitch/scripts/provision-demo-users.sh
docker compose exec -T freeswitch fs_cli -x "global_getvar local_ip_v4"
docker compose exec -T freeswitch fs_cli -x "user_exists id 1001 localhost"
docker compose exec -T freeswitch fs_cli -x "find_user_xml id 1001 localhost"
docker compose exec -T freeswitch fs_cli -x "user_exists id 1002 localhost"
docker compose exec -T freeswitch fs_cli -x "find_user_xml id 1002 localhost"
docker compose exec -T freeswitch fs_cli -x "user_exists id 2001 localhost"
docker compose exec -T freeswitch fs_cli -x "find_user_xml id 2001 localhost"
docker compose exec -T freeswitch fs_cli -x "user_exists id 2001 <runtime-domain>"
docker compose exec -T freeswitch fs_cli -x "find_user_xml id 2001 <runtime-domain>"
docker compose exec -T freeswitch fs_cli -x "xml_locate dialplan context name public"
docker compose exec -T freeswitch fs_cli -x "xml_locate dialplan context name default"
docker compose exec -T freeswitch fs_cli -x "show registrations"
```

If the FreeSWITCH container was recreated, expect the runtime-copied XML files
and SIP registrations to be empty until you run provisioning again and re-register
the browsers.

If `user_data <user>@localhost attr password` returns `-ERR no reply` on this
image, treat `find_user_xml id <user> <domain>` as the authoritative auth
check. The `localhost` alias, the runtime-domain copy, and the demo dialplan
fixture must all expose the expected local-demo routing behavior, not only a
pointer XML entry.

## FreeSWITCH local demo bridge returns 488 or INCOMPATIBLE_DESTINATION

If the call reaches the callee and then fails after answer with:

```text
488 Not Acceptable Here
INCOMPATIBLE_DESTINATION
```

check the local demo dialplan bridge target first.

The safe local path is:

```text
destination_number
-> sofia_contact(*/<destination>@<runtime-domain>)
-> bridge(<exact contact string>)
```

If the log still shows `bridge(user/<extension>@<runtime-domain>)`, the demo
dialplan file is loading too late in the `public` or `default` context.
Re-run the FreeSWITCH provisioning script so it rewrites the live
`public.xml` and `default.xml` files with a single inline local-demo block,
removes any stale earlier copy of that block, and keeps the route before the
stock route in both files. The script also refreshes the runtime domain
substitution so a stale `__RUNTIME_DOMAIN__` token cannot survive a partial
update.

If the resolved contact is empty, the correct failure is `480 Temporarily
Unavailable` with a `USER_NOT_REGISTERED` hangup cause. That means the callee
is not registered yet.

If the contact is not empty but the call still fails after answer, inspect the
SIP/SDP negotiation in the logs for codec, ICE, DTLS, or media-direction
issues. Do not treat a missing Sofia contact as a media bug.
