{{-- Layered, slowly drifting wave band. Colour via text-* on the component. --}}
<div {{ $attributes->merge(['class' => 'pointer-events-none relative h-16 w-full overflow-hidden sm:h-24']) }} aria-hidden="true">
    <svg class="absolute bottom-0 left-0 h-full w-[calc(100%+48px)] animate-drift-slow opacity-40" viewBox="0 0 1440 96" preserveAspectRatio="none">
        <path fill="currentColor" d="M0 48c120 22 240 22 360 0s240-22 360 0 240 22 360 0 240-22 360 0v48H0z" />
    </svg>
    <svg class="absolute bottom-0 left-0 h-[80%] w-[calc(100%+48px)] animate-drift" viewBox="0 0 1440 96" preserveAspectRatio="none">
        <path fill="currentColor" d="M0 56c160-26 320-26 480 0s320 26 480 0 320-26 480 0v40H0z" />
    </svg>
</div>
