<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Today at Labnasan">
            {{ now()->format('l, F j') }} · Approve reservations and handle check-ins and check-outs.
            <x-slot name="actions">
                <a href="{{ route('staff.bookings.index') }}" class="btn-primary">Open booking queue</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        {{-- Stat tiles --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" x-reveal.stagger>
            @foreach ([
                ['pending', 'Pending requests', 'sand', 'Waiting for a decision'],
                ['arrivals', 'Arrivals', 'lagoon', 'Confirmed, ready to check in'],
                ['in_house', 'In house', 'ocean', 'Guests checked in now'],
                ['departures', 'Departures', 'coral', 'Due to check out by 12:00 NN'],
            ] as [$key, $label, $tone, $hint])
                <a href="{{ route('staff.bookings.index', ['tab' => $key]) }}" x-tilt="6"
                   class="card tilt group block p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400">
                    <p class="text-sm font-semibold text-gray-600">{{ $label }}</p>
                    <p @class(['tilt-pop mt-2 font-display text-5xl font-semibold tabular-nums',
                               'text-sand-700' => $tone === 'sand', 'text-lagoon-700' => $tone === 'lagoon',
                               'text-ocean-700' => $tone === 'ocean', 'text-coral-600' => $tone === 'coral'])>{{ $stats[$key] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['Waiting for approval', $pending, 'pending', 'No requests waiting. Nice!'],
                ['Arriving', $arrivals, 'arrivals', 'No arrivals today.'],
                ['Departing', $departures, 'departures', 'No departures due.'],
            ] as [$title, $list, $tab, $empty])
                <section class="card p-5" x-reveal>
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-lg font-semibold text-lagoon-950">{{ $title }}</h2>
                        <a href="{{ route('staff.bookings.index', ['tab' => $tab]) }}" class="link-underline text-sm">See all</a>
                    </div>
                    <ul class="mt-4 divide-y divide-sand-200">
                        @forelse ($list as $booking)
                            <li>
                                <a href="{{ route('staff.bookings.show', $booking) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition hover:bg-sand-50">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-gray-800">{{ $booking->user->name }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $booking->cottage->name }} · {{ $booking->check_in->format('M j') }}–{{ $booking->check_out->format('M j') }}</span>
                                    </span>
                                    <span class="shrink-0 font-mono text-xs text-sand-700">{{ $booking->reference_code }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-gray-500">{{ $empty }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
