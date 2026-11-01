@props(['active'])

@php
$classes = ($active ?? false)
    ? 'text-lagoon-950 after:scale-x-100'
    : 'text-gray-600 hover:text-lagoon-950 after:scale-x-0 hover:after:scale-x-100';
@endphp

<a {{ $attributes->merge(['class' => "relative inline-flex items-center px-3 py-2 text-sm font-semibold transition-colors duration-200
    after:absolute after:inset-x-3 after:-bottom-px after:h-px after:origin-left after:bg-lagoon-950 after:transition-transform after:duration-500 after:ease-expo
    focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400 rounded $classes"]) }}
    @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
