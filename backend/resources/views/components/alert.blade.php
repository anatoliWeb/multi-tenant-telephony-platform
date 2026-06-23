{{-- WHY:
     Unified UI improves usability and reduces cognitive load across admin panel. --}}
@props([
    'type' => 'success',
])

<div {{ $attributes->merge(['class' => "c-alert c-alert--{$type}"]) }}>
    {{ $slot }}
</div>
