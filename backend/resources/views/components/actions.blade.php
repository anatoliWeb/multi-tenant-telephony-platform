{{-- WHY:
     Reusable components ensure consistent UI across admin panel
     and reduce duplication. --}}
<div {{ $attributes->merge(['class' => 'c-actions']) }}>
    {{ $slot }}
</div>
