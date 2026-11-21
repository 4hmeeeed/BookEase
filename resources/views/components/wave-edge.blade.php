{{--
    A soft swell cut along the bottom of a photo, filled with the colour of the
    panel below so the picture seems to meet the water. Set the fill with a
    text-* class (it uses currentColor).
--}}
<svg {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-x-0 bottom-0 h-6 w-full']) }}
     viewBox="0 0 400 24" preserveAspectRatio="none" fill="currentColor" aria-hidden="true">
    <path d="M0 14c40-9 80-9 120 0s80 9 120 0 80-9 120 0c16 3.6 28 5 40 5V24H0Z" />
</svg>
