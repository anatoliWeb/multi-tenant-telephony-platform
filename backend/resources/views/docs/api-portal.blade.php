<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('api-docs.title') }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f6f7fb; color: #1f2937; }
        .container { max-width: 980px; margin: 32px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .title { margin: 0 0 8px; font-size: 28px; }
        .subtitle { margin: 0; color: #6b7280; }
        .pill { display: inline-block; font-size: 12px; background: #e0e7ff; color: #3730a3; padding: 4px 8px; border-radius: 999px; margin-top: 12px; }
        .group-title { margin: 0 0 6px; font-size: 20px; }
        .group-description { margin: 0 0 12px; color: #4b5563; }
        .paths { margin: 0 0 12px 18px; color: #374151; }
        .actions a { text-decoration: none; color: #fff; background: #111827; padding: 8px 12px; border-radius: 8px; display: inline-block; margin-right: 8px; }
        .muted-link { color: #374151; background: #f3f4f6; }
        .lang-switcher { margin-top: 12px; display: inline-flex; gap: 8px; }
        .lang-switcher a { color: #374151; text-decoration: none; border: 1px solid #d1d5db; border-radius: 999px; padding: 3px 10px; font-size: 12px; background: #fff; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1 class="title">{{ __('api-docs.title') }}</h1>
        <p class="subtitle">{{ __('api-docs.subtitle') }}</p>
        <span class="pill">{{ __('api-docs.access_states.' . $accessState) }}</span>
        @if($hasFullAccess)
            <span class="pill">{{ __('api-docs.full_access_notice') }}</span>
        @endif
        <div class="lang-switcher">
            <a href="/docs/api/portal?lang=en">EN</a>
            <a href="/docs/api/portal?lang=uk">UK</a>
            <a href="/docs/api/portal?lang=de">DE</a>
        </div>
        <div class="actions" style="margin-top:16px;">
            @if($hasFullAccess)
                <a href="{{ $docsUiUrl }}">{{ __('api-docs.open_swagger') }}</a>
                <a class="muted-link" href="{{ $docsJsonUrl }}">{{ __('api-docs.open_json') }}</a>
            @endif
            <a class="muted-link" href="{{ $filteredDocsJsonUrl }}">{{ __('api-docs.open_filtered_spec') }}</a>
        </div>
        <p class="subtitle" style="margin-top:12px;">
            Raw Swagger UI and raw OpenAPI JSON are available only for full-access users.
        </p>
    </div>

    @if(count($visibleGroups) === 0)
        <div class="card">
            <h2 class="group-title">{{ __('api-docs.empty_title') }}</h2>
            <p class="group-description">{{ __('api-docs.empty_description') }}</p>
        </div>
    @else
        @foreach($visibleGroups as $groupKey => $group)
            <div class="card" data-group="{{ $groupKey }}">
                <h2 class="group-title">{{ $group['label'] ?? $groupKey }}</h2>
                <p class="group-description">{{ $group['description'] ?? '' }}</p>
                @if(!empty($group['paths']) && is_array($group['paths']))
                    <ul class="paths">
                        @foreach($group['paths'] as $pathPattern)
                            <li>{{ $pathPattern }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif
</div>
</body>
</html>
