@props(['active'])

@php
$classes = ($active ?? false)
    ? 'bg-lagoon-950/[0.05] text-lagoon-950 before:bg-coral-500'
    : 'text-gray-600 hover:bg-lagoon-950/[0.03] hover:text-lagoon-950 before:bg-transparent';
@endphp

<a {{ $attributes->merge(['class' => "relative flex w-full items-center gap-3 py-3 pe-4 ps-4 text-start text-base font-medium transition duration-200 ease-out-soft focus:outline-none
    before:h-1.5 before:w-1.5 before:shrink-0 before:rounded-full $classes"]) }}
    @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
