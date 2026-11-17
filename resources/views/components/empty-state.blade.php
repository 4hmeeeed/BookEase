{{-- A quiet, nautical "nothing here" — a small line drawing over one line of text. --}}
@props(['icon' => 'compass'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 px-6 py-8 text-center']) }}>
    <svg class="h-14 w-14 text-lagoon-900/40" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($icon)
            @case('buoy')
                <path d="M32 6v8M28 6h8" />
                <path d="M25 14h14l4 26H21Z" />
                <path d="M23 27h18" />
                <path d="M6 48c4.5-3 9-3 13.5 0s9 3 13.5 0 9-3 13.5 0 9 3 13.5 0" />
                <path d="M14 56c4.5-3 9-3 13.5 0s9 3 13.5 0 9-3 13.5 0" />
                @break
            @case('anchor')
                <circle cx="32" cy="11" r="4" />
                <path d="M32 15v40M22 23h20" />
                <path d="M12 39c1.5 9.5 9.5 16 20 16s18.5-6.5 20-16" />
                <path d="m12 39-3.5 5M12 39l6 1.5M52 39l3.5 5M52 39l-6 1.5" />
                @break
            @default
                <circle cx="32" cy="32" r="24" />
                <circle cx="32" cy="32" r="2.5" />
                <path d="M32 10 35.5 28.5 54 32 35.5 35.5 32 54 28.5 35.5 10 32 28.5 28.5Z" />
                <path d="M32 4v4M32 56v4M4 32h4M56 32h4" />
        @endswitch
    </svg>
    <p class="max-w-xs text-sm text-gray-600">{{ $slot }}</p>
</div>
