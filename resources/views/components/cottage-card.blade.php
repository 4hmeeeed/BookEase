@props(['cottage', 'total' => null, 'nights' => null, 'checkIn' => null, 'checkOut' => null, 'size' => 'base'])

@php
    $href = route('cottages.show', array_filter([
        'cottage' => $cottage,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]));
@endphp

<a href="{{ $href }}" x-tilt="4"
   {{ $attributes->merge(['class' => 'card tilt group flex flex-col overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400']) }}>
    <div @class(['relative', 'lg:min-h-[22rem] lg:flex-1' => $size === 'feature'])>
        <x-cottage-image :cottage="$cottage" morph @class(['aspect-[4/3]', 'lg:absolute lg:inset-0 lg:aspect-auto' => $size === 'feature']) />
        <span class="tilt-pop absolute left-3 top-3 rounded bg-cream/95 px-2 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-lagoon-900 shadow-sm">
            {{ $cottage->type->label() }}
        </span>
        @if ($total)
            <span class="tilt-pop badge badge-confirmed absolute right-3 top-3 bg-cream shadow-sm">
                <span class="badge-dot is-live"></span>Free for your dates
            </span>
        @endif
    </div>

    <div @class(['flex flex-col p-5', 'flex-1' => $size !== 'feature'])>
        <h3 class="tilt-pop mb-5 font-serif text-xl leading-snug text-lagoon-950 [&:has(+p)]:mb-0">{{ $cottage->name }}</h3>
        @if ($cottage->description)
            <p class="mb-5 mt-1.5 line-clamp-2 text-sm leading-relaxed text-gray-600">{{ $cottage->description }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 border-t border-lagoon-950/10 pt-4">
            <span class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                <svg class="h-4 w-4 text-lagoon-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.5" /><path stroke-linecap="round" d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5" /></svg>
                Up to {{ $cottage->capacity }} {{ Str::plural('guest', $cottage->capacity) }}
            </span>
            <span class="tilt-pop text-right">
                @if ($total)
                    <span class="tabular block text-xl font-semibold tracking-tight text-lagoon-950">{{ \App\Support\Money::format($total) }}</span>
                    <span class="block text-xs text-gray-500">{{ $nights }} {{ Str::plural('night', $nights) }} · {{ $cottage->formatted_price }}/night</span>
                @else
                    <span class="tabular block text-xl font-semibold tracking-tight text-lagoon-950">{{ $cottage->formatted_price }}</span>
                    <span class="block text-xs text-gray-500">per night</span>
                @endif
            </span>
        </div>
    </div>
</a>
