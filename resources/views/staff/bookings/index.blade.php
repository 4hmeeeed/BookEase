<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Front desk" title="Booking queue">
            Pending requests are listed oldest first so no guest waits too long.
        </x-page-header>
    </x-slot>

    @php
        $tabs = [
            'pending' => 'Pending', 'arrivals' => 'Arrivals', 'in_house' => 'In house', 'departures' => 'Departures',
            'upcoming' => 'Upcoming', 'history' => 'History', 'all' => 'All',
        ];
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" aria-label="Booking filters">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('staff.bookings.index', array_filter(['tab' => $key, 'q' => $search])) }}"
                       @class(['inline-flex shrink-0 items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold transition-all duration-200 ease-out-soft active:scale-95',
                               'bg-lagoon-950 text-cream' => $tab === $key,
                               'text-gray-600 hover:bg-sand-100 hover:text-lagoon-900' => $tab !== $key])>
                        {{ $label }}
                        <span @class(['rounded-md px-1.5 text-xs', 'bg-lagoon-600' => $tab === $key, 'bg-sand-200 text-gray-700' => $tab !== $key])>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="relative w-full lg:w-80">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Reference, guest name, or email" class="field !py-2 pl-10">
            </form>
        </div>

        @if ($bookings->isEmpty())
            <div class="card mt-8 animate-pop-in p-12 text-center">
                <p class="font-serif text-2xl font-semibold text-lagoon-950">Nothing here</p>
                <p class="mt-1 text-gray-600">{{ $search ? 'No bookings match your search.' : 'There are no bookings in this list right now.' }}</p>
            </div>
        @else
            <ul class="mt-6 space-y-3" x-reveal.stagger>
                @foreach ($bookings as $booking)
                    <li>
                        <a href="{{ route('staff.bookings.show', $booking) }}"
                           class="card group grid gap-3 p-4 transition-all duration-300 ease-out-soft hover:-translate-y-0.5 hover:shadow-lift sm:grid-cols-[8rem_1fr_auto_auto] sm:items-center">
                            <span class="font-mono text-sm font-semibold tracking-wider text-sand-700">{{ $booking->reference_code }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-gray-900">{{ $booking->user->name }}</span>
                                <span class="block truncate text-sm text-gray-600">
                                    {{ $booking->cottage->name }} · {{ $booking->guests_count }} {{ Str::plural('guest', $booking->guests_count) }}
                                </span>
                            </span>
                            <span class="text-sm text-gray-700">
                                {{ $booking->check_in->format('M j') }} → {{ $booking->check_out->format('M j, Y') }}
                                <span class="block text-xs text-gray-500">{{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }} · {{ \App\Support\Money::format($booking->total_price) }}</span>
                                <span @class(['block text-xs font-semibold',
                                              'text-lagoon-700' => $booking->payment_status->value === 'paid',
                                              'text-sand-700' => $booking->payment_status->value === 'partially_paid',
                                              'text-gray-400' => ! in_array($booking->payment_status->value, ['paid', 'partially_paid'], true)])>
                                    {{ $booking->payment_status->label() }}
                                </span>
                            </span>
                            <x-booking-status :status="$booking->status" class="justify-self-start sm:justify-self-end" />
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $bookings->links() }}</div>
        @endif
    </div>
</x-app-layout>
