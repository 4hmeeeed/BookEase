@props(['active'])

@php
$classes = ($active ?? false)
    ? 'bg-lagoon-700 text-cream shadow-lagoon-glow'
    : 'text-gray-600 hover:bg-sand-100 hover:text-lagoon-900';
@endphp

<a {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200 ease-out-soft active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400 $classes"]) }}>
    {{ $slot }}
</a>
