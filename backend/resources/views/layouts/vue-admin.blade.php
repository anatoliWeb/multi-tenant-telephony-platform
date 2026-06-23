<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Multi-Tenant Telephony Platform Admin')</title>

    {{-- 
        Hybrid migration entrypoint.
        WHY:
        - We intentionally run Blade and Vue side-by-side during migration.
        - This avoids risky big-bang rewrites and allows safe page-by-page rollout.
        - Legacy Blade layouts remain untouched while new pages can opt into Vue.
    --}}
    {{--
        Vue entrypoint includes app.scss directly in main.ts.
        WHY:
        Reusing the same SCSS theme inside SPA keeps visual consistency with
        legacy Blade pages and avoids duplicated CSS injection during migration.
    --}}
    @vite(['resources/js/main.ts'])
</head>
<body>
    {{--
        Dedicated Vue mount boundary for admin migration pages.
        Only pages extending this layout will initialize Vue SPA shell.
    --}}
    <div id="app"></div>
</body>
</html>
