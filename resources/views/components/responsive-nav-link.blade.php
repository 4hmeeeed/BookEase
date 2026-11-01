@props(['active'])

@php
$classes = ($active ?? false)
    ? 'border-coral-500 bg-sand-100 text-lagoon-900'
    : 'border-transparent text-gray-600 hover:border-sand-300 hover:bg-sand-50 hover:text-lagoon-900';
@endphp

<a {{ $attributes->merge(['class' => "block w-full border-l-4 py-2.5 pe-4 ps-4 text-start text-base font-medium transition duration-200 ease-out-soft focus:outline-none $classes"]) }}>
    {{ $slot }}
</a>
