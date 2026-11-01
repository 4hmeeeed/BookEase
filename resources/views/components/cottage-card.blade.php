@props(['cottage', 'total' => null, 'nights' => null, 'checkIn' => null, 'checkOut' => null])

@php
    $href = route('cottages.show', array_filter([
        'cottage' => $cottage,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]));
@endphp

<a href="{{ $href }}" x-tilt="6"
   {{ $attributes->merge(['class' => 'card tilt group flex flex-col overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400']) }}>
    <div class="relative">
        <x-cottage-image :cottage="$cottage" class="aspect-[4/3]" />
        <span class="tilt-pop absolute left-3 top-3 rounded-full bg-cream/90 px-3 py-1 text-xs font-semibold text-lagoon-800 shadow-sm backdrop-blur">
            {{ $cottage->type->label() }}
        </span>
        @if ($total)
            <span class="tilt-pop badge badge-confirmed absolute right-3 top-3 bg-cream/95 shadow-sm">
                <span class="badge-dot is-live"></span>Free for your dates
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-5">
        <h3 class="tilt-pop font-display text-xl font-semibold text-lagoon-950">{{ $cottage->name }}</h3>
        @if ($cottage->description)
            <p class="mt-1.5 line-clamp-2 text-sm text-gray-600">{{ $cottage->description }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
            <span class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                <svg class="h-4 w-4 text-lagoon-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5" /><path stroke-linecap="round" d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5" /></svg>
                Up to {{ $cottage->capacity }} {{ Str::plural('guest', $cottage->capacity) }}
            </span>
            <span class="tilt-pop text-right">
                @if ($total)
                    <span class="block font-display text-2xl font-semibold text-coral-600">{{ \App\Support\Money::format($total) }}</span>
                    <span class="block text-xs text-gray-500">{{ $nights }} {{ Str::plural('night', $nights) }} · {{ $cottage->formatted_price }}/night</span>
                @else
                    <span class="block font-display text-2xl font-semibold text-coral-600">{{ $cottage->formatted_price }}</span>
                    <span class="block text-xs text-gray-500">per night</span>
                @endif
            </span>
        </div>
    </div>
</a>
