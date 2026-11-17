{{-- Line icons for the staff/owner console (24px grid, 1.8 stroke, inherits colour). --}}
@props(['name'])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('overview')
            <path d="M4 4h6v9H4zM14 4h6v5h-6zM14 13h6v7h-6zM4 17h6v3H4z" />
            @break
        @case('desk')
            <path d="M3.5 17.5h17M5.5 17.5a6.5 6.5 0 0 1 13 0M12 9V7M10 7h4" />
            @break
        @case('bookings')
            <path d="M7 3.5v3M17 3.5v3M4.5 9h15M5.5 6h13a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" />
            @break
        @case('cottages')
            <path d="M3 11 12 4l9 7M5 10v10h14V10M10 20v-5h4v5" />
            @break
        @case('staff')
            <circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5" />
            @break
        @case('reports')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            @break
        @case('chatbot')
            <path d="M4 5h16v11H9l-5 4V5Z" /><path d="M8 9h8M8 12h5" />
            @break
        @case('bell')
            <path d="M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15Z" /><path d="M10 20.5a2 2 0 0 0 4 0" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
            @break
        @case('more')
            <path d="M5 12h.01M12 12h.01M19 12h.01" stroke-width="3" />
            @break
        @case('profile')
            <circle cx="12" cy="8" r="4" /><path d="M4 20.5a8 8 0 0 1 16 0" />
            @break
        @case('logout')
            <path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" />
            @break
    @endswitch
</svg>
