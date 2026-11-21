{{-- A booking as a postcard: the cottage on top, cut along a swell, the stay written below. --}}
<a href="{{ route('guest.bookings.show', $booking) }}" x-tilt="4"
   class="card tilt group flex h-full flex-col overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400">
    <div class="relative h-36 shrink-0 sm:h-40">
        <x-cottage-image :cottage="$booking->cottage" class="absolute inset-0 h-full w-full" />
        <x-booking-status :status="$booking->status" class="tilt-pop absolute right-3 top-3 shadow-sm" />
        <x-wave-edge class="-mb-px h-5 text-white" />
    </div>

    <div class="flex flex-1 flex-col px-4 pb-4 pt-1">
        <div class="flex-1">
            <h3 class="tilt-pop truncate font-serif text-xl text-lagoon-950">{{ $booking->cottage->name }}</h3>
            <p class="mt-0.5 text-sm text-gray-600">
                {{ $booking->check_in->format('M j') }} – {{ $booking->check_out->format('M j, Y') }}
                · {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }}
            </p>
        </div>

        <div class="mt-4 flex items-end justify-between gap-3 border-t border-dashed border-sand-200 pt-3">
            <span class="text-xs tracking-wider text-sand-700 tabular-nums">{{ $booking->reference_code }}</span>
            <span class="font-display text-xl tabular-nums text-lagoon-950">{{ \App\Support\Money::format($booking->total_price) }}</span>
        </div>
    </div>
</a>
