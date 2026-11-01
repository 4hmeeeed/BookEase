<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Welcome back" :title="'Maayong adlaw, '.Str::before(Auth::user()->name, ' ').'!'">
            Plan your stay at Labnasan Resort: browse cottages, check open dates, and keep track of your bookings.
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        @if ($next)
            <a href="{{ route('guest.bookings.show', $next) }}" x-tilt="3"
               class="card tilt group relative block overflow-hidden bg-lagoon-900 p-6 text-cream sm:p-8">
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="tilt-pop">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-coral-300">Your next stay</p>
                        <h2 class="mt-2 font-display text-3xl font-semibold">{{ $next->cottage->name }}</h2>
                        <p class="mt-1 text-lagoon-100">
                            {{ $next->check_in->format('D, M j') }} → {{ $next->check_out->format('D, M j, Y') }}
                            · {{ $next->nights() }} {{ Str::plural('night', $next->nights()) }}
                        </p>
                    </div>
                    <div class="tilt-pop flex items-center gap-4">
                        <span class="font-mono text-sm tracking-wider text-sand-200">{{ $next->reference_code }}</span>
                        <x-booking-status :status="$next->status" />
                    </div>
                </div>
                @php $daysToGo = (int) today()->diffInDays($next->check_in, false); @endphp
                @if ($daysToGo > 0)
                    <p class="relative mt-6 text-sm text-lagoon-100/80">
                        {{ $daysToGo }} {{ Str::plural('day', $daysToGo) }} to go
                    </p>
                @endif
            </a>
        @endif

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
            <x-module-card title="Browse cottages" tone="lagoon" :href="route('cottages.index')">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11 12 4l9 7M5 10v10h14V10M10 20v-5h4v5" /></svg>
                </x-slot>
                See every cottage with its capacity, amenities, and current rates, then pick open dates on the calendar.
            </x-module-card>

            <x-module-card title="My bookings" tone="coral" :href="route('guest.bookings.index')">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path stroke-linecap="round" d="M3.5 10h17M8 3v4M16 3v4" /></svg>
                </x-slot>
                {{ $bookingCount ? "You have {$bookingCount} ".Str::plural('booking', $bookingCount).'.' : 'No bookings yet.' }}
                Follow each one from <em>pending</em> to <em>confirmed</em>.
            </x-module-card>

            <x-module-card title="Payments & receipts" tone="ocean" :href="route('guest.bookings.index')">
                <x-slot name="icon">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2.5" /><path stroke-linecap="round" d="M3 10h18M7 15h4" /></svg>
                </x-slot>
                Pay securely with GCash or Maya and download an official e-receipt after every successful payment.
            </x-module-card>
        </div>
    </div>
</x-app-layout>
