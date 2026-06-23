# OpenAPI Documentation Generator

## Generator Choice
- Package: `dedoc/scramble`
- Why: Laravel-native OpenAPI generation from routes, controllers, FormRequests and resources, with low annotation overhead.

## Routes
- UI: `/docs/api`
- JSON spec: `/docs/api.json`

## Source of Truth
OpenAPI output is generated from:
- Laravel routes (`routes/api.php`)
- FormRequest validation contracts (`app/Http/Requests/**`)
- API resources (`app/Http/Resources/**`)
- Scramble transformers/security/schema wiring in `AppServiceProvider`
- preparation inventory in `docs/api/openapi-preparation.md`

## Auth and Security in Docs
- `BearerAuth` for protected API routes
- `SanctumSession` for session-first browser flow
- `ExternalChatToken` for external chat API routes
- `WebhookSignature` + `WebhookTimestamp` for incoming webhook verification routes

## Access Control
- Docs middleware is configured in `config/scramble.php`
- `ApiDocsAccessMiddleware` protects `/docs/api` and `/docs/api.json`
- `API_DOCS_LOCAL_BYPASS=false` is the secure default (strict local policy).
- `API_DOCS_LOCAL_BYPASS=false` enforces real permission policy even in local/testing.
- Set `API_DOCS_LOCAL_BYPASS=true` only when you explicitly need local development bypass.
- Raw docs (`/docs/api`, `/docs/api.json`) require full docs gate (`api.docs.view.full` or admin).
- Permission-aware routes (`/docs/api/portal`, `/docs/api.filtered.json`) require `api.docs.view`.

## Permission-Aware API Documentation
- Centralized permission map is defined in `config/api-docs.php`.
- Group resolver/service: `App\Services\ApiDocsPermissionService`.
- Baseline permissions:
  - `api.docs.view`: can open docs routes.
  - `api.docs.view.full`: can see all mapped API groups in future filtered-spec mode.
- Current step is foundation only:
  - no runtime OpenAPI JSON filtering is applied yet.
  - map is used as contract for upcoming permission-aware spec slicing.

## Permission-Aware Docs Portal
- Portal route: `/docs/api/portal`
- Access:
  - local/testing: available for development workflow
  - non-local: protected by `ApiDocsAccessMiddleware` and `api.docs.view`
- Visibility rules:
  - `api.docs.view.full` users see all mapped API groups.
  - other docs users see only groups allowed by `ApiDocsPermissionService`.
  - if user has docs access but no mapped endpoint permissions, portal renders a safe empty state.
- Scope of this step:
  - permission-aware navigation/entry mode is enabled on portal.
  - raw `/docs/api` and `/docs/api.json` are full-access only.
  - portal provides language switcher links:
    - `/docs/api/portal?lang=en`
    - `/docs/api/portal?lang=uk`
    - `/docs/api/portal?lang=de`

## Permission-Aware Filtered OpenAPI Spec
- Base spec: `/docs/api.json` (full Scramble output).
- Filtered spec: `/docs/api.filtered.json` (user-scoped visibility mode).
- Access control:
  - docs access still requires `api.docs.view` in non-local.
  - `api.docs.view.full` keeps full path visibility.
- Filtering source:
  - `config/api-docs.php` groups map
  - `App\Services\ApiDocsPermissionService`
  - `App\Services\ApiDocsOpenApiFilterService`
- Filtering scope in current implementation:
  - filters `paths` by current authenticated docs user permissions.
  - keeps valid OpenAPI root (`openapi`, `info`, `paths`, `components`).
  - internal/hidden routes remain excluded.
- Known limitation:
  - `components` are intentionally not aggressively pruned yet and may include broader schemas than visible paths.

## Swagger UI Language Limitation
- `/docs/api` is the raw Scramble/Starlight Swagger UI.
- Raw Swagger UI does not use admin i18n labels from the Vue Admin app.
- Permission-aware localized entrypoint is `/docs/api/portal`.
- Use `/docs/api/portal?lang=en|uk|de` for localized portal labels.
- Treat raw Swagger as a technical full-access view.

## Multilingual API Docs Labels
- Vue Admin labels (dashboard shortcut + sidebar item) use frontend i18n keys in:
  - `resources/js/shared/i18n/locales/en/common.ts`
  - `resources/js/shared/i18n/locales/uk/common.ts`
  - `resources/js/shared/i18n/locales/de/common.ts`
- Docs portal labels use Laravel language files:
  - `lang/en/api-docs.php`
  - `lang/uk/api-docs.php`
  - `lang/de/api-docs.php`
- Supported locales: `en`, `uk`, `de`.
- Group labels/descriptions are mapped via `config/api-docs.php` (`label_key` / `description_key`).
- Fallback behavior:
  - portal attempts current locale first,
  - then fallback locale (`app.fallback_locale`, default `en`),
  - then config label/description fallback when translation key is missing.

## How to Verify Generator Workflow
Run:
- `composer test:openapi`
- `php -d memory_limit=512M artisan test --filter=OpenApiRouteContract --stop-on-failure`

## Local Security Verification
1. Set `API_DOCS_LOCAL_BYPASS=false`
2. Run `php artisan config:clear`
3. In incognito:
   - `/docs/api` -> denied
   - `/docs/api.json` -> denied
   - `/docs/api/portal` -> denied
4. Login with `api.docs.view`:
   - `/docs/api/portal` -> allowed
   - `/docs/api.filtered.json` -> allowed
   - `/docs/api` -> denied
5. Login with `api.docs.view.full` (or admin):
   - `/docs/api` -> allowed
   - `/docs/api.json` -> allowed

Manual inspect:
- Open `/docs/api` in browser
- Open `/docs/api.json` and verify OpenAPI root fields (`openapi`, `info`, `paths`, `components`)

## Operational Notes
- `test:openapi` is the primary regression gate for docs contract.
- Keep API response envelope and validation error format consistent to preserve generated contract quality.
- Keep docs/spec free of sensitive internals:
  - no `token_hash`, webhook secret values, raw signatures, storage internals.

## What Not To Do
- Do not introduce a second Swagger package (no L5-Swagger).
- Do not add manual annotations everywhere when routes/requests/resources already describe the contract.
- Do not expose docs routes publicly in non-local environments.
