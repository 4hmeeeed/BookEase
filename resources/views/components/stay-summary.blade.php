@props(['cottage', 'checkIn', 'checkOut', 'nights', 'total', 'guests' => null])

{{-- Cottage + dates + bcmath total. Used on the reservation form and booking pages. --}}
<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <x-cottage-image :cottage="$cottage" class="aspect-[16/9]" />

    <div class="p-5 sm:p-6">
        <p class="eyebrow">{{ $cottage->type->label() }}</p>
        <h3 class="mt-1 font-serif text-2xl font-semibold text-lagoon-950">{{ $cottage->name }}</h3>

        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-2xl bg-sand-50 p-3">
                <dt class="text-[0.65rem] font-semibold uppercase tracking-widest text-sand-600">Check-in</dt>
                <dd class="mt-0.5 font-semibold text-lagoon-950">{{ $checkIn->format('D, M j, Y') }}</dd>
                <dd class="text-xs text-gray-500">from 2:00 PM</dd>
            </div>
            <div class="rounded-2xl bg-sand-50 p-3">
                <dt class="text-[0.65rem] font-semibold uppercase tracking-widest text-sand-600">Check-out</dt>
                <dd class="mt-0.5 font-semibold text-lagoon-950">{{ $checkOut->format('D, M j, Y') }}</dd>
                <dd class="text-xs text-gray-500">by 12:00 NN</dd>
            </div>
        </dl>

        <div class="mt-5 space-y-2 border-t border-dashed border-sand-300 pt-4 text-sm">
            <div class="flex justify-between text-gray-600">
                <span>{{ $cottage->formatted_price }} × {{ $nights }} {{ Str::plural('night', $nights) }}</span>
                <span>{{ \App\Support\Money::format($total) }}</span>
            </div>
            @if ($guests)
                <div class="flex justify-between text-gray-600">
                    <span>Guests</span><span>{{ $guests }}</span>
                </div>
            @endif
            <div class="flex items-end justify-between pt-2">
                <span class="font-semibold text-lagoon-950">Total</span>
                <span class="font-display text-3xl font-semibold text-coral-600">{{ \App\Support\Money::format($total) }}</span>
            </div>
        </div>

        {{ $slot }}
    </div>
</div>
