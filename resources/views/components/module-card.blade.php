@props(['title', 'href' => null, 'tone' => 'lagoon', 'status' => null])

@php
    $tones = [
        'lagoon' => 'bg-lagoon-50 text-lagoon-700 ring-lagoon-100',
        'coral' => 'bg-coral-50 text-coral-600 ring-coral-100',
        'ocean' => 'bg-ocean-50 text-ocean-700 ring-ocean-100',
        'sand' => 'bg-sand-100 text-sand-700 ring-sand-200',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif x-tilt
    {{ $attributes->merge(['class' => 'card tilt group block p-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400']) }}>
    <div class="tilt-pop flex items-start justify-between gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl ring-1 transition-transform duration-500 ease-spring group-hover:-rotate-6 group-hover:scale-110 {{ $tones[$tone] ?? $tones['lagoon'] }}">
            {{ $icon ?? '' }}
        </span>

        @if ($status)
            <span class="badge badge-pending"><span class="badge-dot"></span>{{ $status }}</span>
        @endif
    </div>

    <h3 class="tilt-pop mt-5 font-display text-xl font-semibold text-lagoon-950">{{ $title }}</h3>
    <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ $slot }}</p>
</{{ $tag }}>
