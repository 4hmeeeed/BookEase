@props(['title', 'href' => null, 'tone' => 'lagoon', 'status' => null])

@php
    $tones = [
        'lagoon' => 'text-lagoon-700 border-lagoon-200',
        'coral' => 'text-coral-600 border-coral-200',
        'ocean' => 'text-ocean-700 border-ocean-200',
        'sand' => 'text-sand-700 border-sand-300',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif x-tilt
    {{ $attributes->merge(['class' => 'card tilt group flex flex-col p-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400']) }}>
    <div class="tilt-pop flex items-start justify-between gap-4">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md border bg-white {{ $tones[$tone] ?? $tones['lagoon'] }}">
            {{ $icon ?? '' }}
        </span>

        @if ($status)
            <span class="badge badge-pending"><span class="badge-dot"></span>{{ $status }}</span>
        @endif
    </div>

    <h3 class="tilt-pop mt-6 font-serif text-xl text-lagoon-950">{{ $title }}</h3>
    <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ $slot }}</p>

    @if ($href)
        <span class="mt-auto pt-5 text-sm font-semibold text-lagoon-800">Open <span class="arrow">&rarr;</span></span>
    @endif
</{{ $tag }}>
