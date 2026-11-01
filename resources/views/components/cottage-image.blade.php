@props(['cottage'])

{{-- Uploaded photo, or an illustrated seaside placeholder tinted by unit type. --}}
@php
    $tint = match ($cottage->type?->value) {
        'room' => 'from-ocean-200 via-ocean-100 to-sand-100',
        'villa' => 'from-lagoon-300 via-lagoon-100 to-sand-100',
        'cabana' => 'from-coral-200 via-sand-100 to-sand-200',
        default => 'from-sand-200 via-coral-100 to-ocean-100',
    };
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-sand-100']) }}>
    @if ($cottage->image_url)
        <img src="{{ $cottage->image_url }}" alt="{{ $cottage->name }}" loading="lazy"
             class="h-full w-full object-cover transition-transform duration-700 ease-out-soft group-hover:scale-105">
    @else
        <div class="absolute inset-0 bg-gradient-to-b {{ $tint }}" aria-hidden="true"></div>
        <div class="absolute right-[18%] top-[18%] h-12 w-12 rounded-full bg-coral-300/80 blur-[1px]" aria-hidden="true"></div>
        <svg class="absolute inset-x-0 bottom-[22%] mx-auto h-1/2 text-lagoon-900/25 transition-transform duration-700 ease-out-soft group-hover:-translate-y-1" viewBox="0 0 120 80" fill="currentColor" aria-hidden="true">
            <path d="M10 44 60 12l50 32h-10v30H20V44z" />
            <rect x="50" y="52" width="20" height="22" rx="2" class="text-cream" fill="currentColor" opacity=".7" />
        </svg>
        <div class="absolute inset-x-0 bottom-0 text-lagoon-700/40"><x-waves class="!h-10" /></div>
        <span class="absolute bottom-2 left-3 text-[0.65rem] font-semibold uppercase tracking-widest text-lagoon-900/60">No photo yet</span>
    @endif
</div>
