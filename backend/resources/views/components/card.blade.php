{{-- WHY:
     Reusable components ensure consistent UI across admin panel
     and reduce duplication. --}}
<section {{ $attributes->merge(['class' => 'c-card']) }}>
    {{ $slot }}
</section>
