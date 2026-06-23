{{-- WHY:
     Reusable components ensure consistent UI across admin panel
     and reduce duplication. --}}
@props([
    'title',
    'subtitle' => null,
])

<header class="c-page-header">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="c-page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        {{ $actions }}
    @endisset
</header>
