@props(['items' => []])

@if(!empty($items))
<nav class="c-breadcrumbs" aria-label="Breadcrumb">
    <ol class="c-breadcrumbs__list">
        @foreach($items as $item)
            @php
                $isLast = $loop->last;
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? null;
            @endphp

            <li class="c-breadcrumbs__item {{ $isLast ? 'is-active' : '' }}">
                @if(!$isLast && $url)
                    <a href="{{ $url }}" class="c-breadcrumbs__link">{{ $label }}</a>
                @else
                    <span class="c-breadcrumbs__current">{{ $label }}</span>
                @endif

                @unless($isLast)
                    <span class="c-breadcrumbs__separator" aria-hidden="true">
                        <svg viewBox="0 0 20 20"><path d="m7 4 6 6-6 6"/></svg>
                    </span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
@endif
