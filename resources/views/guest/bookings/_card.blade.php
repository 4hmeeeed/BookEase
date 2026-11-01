<a href="{{ route('guest.bookings.show', $booking) }}" x-tilt="5"
   class="card tilt group flex gap-4 overflow-hidden p-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400 sm:p-4">
    <x-cottage-image :cottage="$booking->cottage" class="h-24 w-24 shrink-0 rounded-2xl sm:h-28 sm:w-32" />

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-mono text-xs font-semibold tracking-wider text-sand-700">{{ $booking->reference_code }}</span>
            <x-booking-status :status="$booking->status" class="tilt-pop" />
        </div>
        <h3 class="tilt-pop mt-1 truncate font-serif text-lg font-semibold text-lagoon-950">{{ $booking->cottage->name }}</h3>
        <p class="text-sm text-gray-600">
            {{ $booking->check_in->format('M j') }} – {{ $booking->check_out->format('M j, Y') }}
            · {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }}
        </p>
        <p class="mt-1 font-semibold text-coral-600">{{ \App\Support\Money::format($booking->total_price) }}</p>
    </div>
</a>
