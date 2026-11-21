<x-app-layout>
    @php
        $swell = 'M0 40Q90 18 180 40T360 40T540 40T720 40T900 40T1080 40T1260 40T1440 40V80H0Z';
    @endphp

    {{-- Dawn over the bay: the one moving thing on the page is the sea. --}}
    <section class="horizon relative isolate overflow-hidden">
        <div aria-hidden="true" class="horizon-sun absolute bottom-6 right-[12%] -z-10 h-20 w-20 rounded-full sm:h-28 sm:w-28"></div>

        <div class="mx-auto max-w-7xl px-4 pb-20 pt-10 sm:px-6 sm:pb-24 sm:pt-14 lg:px-8">
            <p class="font-serif text-sm italic text-lagoon-700">Labnasan Resort · Basey, Samar</p>
            <h1 class="mt-2 max-w-[14ch] font-display text-[2.6rem] leading-[1.02] text-lagoon-950 sm:text-6xl">
                Maayong adlaw, {{ Str::before(Auth::user()->name, ' ') }}!
            </h1>
            <p class="mt-4 max-w-md text-[0.95rem] leading-relaxed text-lagoon-900/75">
                Plan your stay at Labnasan Resort: browse cottages, check open dates, and keep track of your bookings.
            </p>
        </div>

        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-20 overflow-hidden sm:h-24">
            <svg class="swell absolute bottom-3 h-full text-lagoon-300/50" style="--swell-speed: 19s" viewBox="0 0 1440 80" preserveAspectRatio="none" fill="currentColor"><path d="{{ $swell }}" /></svg>
            <svg class="swell swell-reverse absolute bottom-1 h-[85%] text-lagoon-500/35" style="--swell-speed: 27s" viewBox="0 0 1440 80" preserveAspectRatio="none" fill="currentColor"><path d="{{ $swell }}" /></svg>
            <svg class="swell absolute -bottom-1 h-[60%] text-cream" style="--swell-speed: 38s" viewBox="0 0 1440 80" preserveAspectRatio="none" fill="currentColor"><path d="{{ $swell }}" /></svg>
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-8 sm:px-6 lg:px-8">
        @if ($next)
            @php $daysToGo = (int) today()->diffInDays($next->check_in, false); @endphp

            {{-- The next stay as a postcard: the cottage up top, meeting the water. --}}
            <a href="{{ route('guest.bookings.show', $next) }}" x-tilt="2"
               class="card tilt group relative -mt-2 block overflow-hidden border-0 bg-lagoon-900 text-cream shadow-lift focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400 lg:grid lg:grid-cols-5">
                <div class="relative h-48 sm:h-60 lg:col-span-2 lg:h-full">
                    <x-cottage-image :cottage="$next->cottage" class="absolute inset-0 h-full w-full" />
                    <div class="absolute inset-0 bg-gradient-to-t from-lagoon-950/45 via-transparent to-transparent lg:hidden" aria-hidden="true"></div>
                    <x-booking-status :status="$next->status" class="absolute right-4 top-4 shadow-sm" />
                    <x-wave-edge class="-mb-px text-lagoon-900 lg:hidden" />
                </div>

                <div class="relative px-5 pb-6 pt-3 sm:px-7 lg:col-span-3 lg:py-8">
                    <p class="font-serif text-sm italic text-sand-200">Your next stay</p>
                    <h2 class="mt-1 font-display text-3xl leading-tight sm:text-4xl">{{ $next->cottage->name }}</h2>

                    {{-- Check-in and check-out as two tide marks joined by the waterline. --}}
                    <div class="mt-5 flex items-end gap-3">
                        <div>
                            <p class="text-xs text-lagoon-100/70">{{ $next->check_in->format('D') }}</p>
                            <p class="stay-day">{{ $next->check_in->format('j') }}</p>
                            <p class="mt-1 text-sm text-lagoon-100">{{ $next->check_in->format('M') }}</p>
                        </div>
                        <div class="mb-7 flex flex-1 items-center gap-2 text-lagoon-300/70" aria-hidden="true">
                            <span class="h-px flex-1 bg-[repeating-linear-gradient(90deg,currentColor_0_6px,transparent_6px_11px)]"></span>
                            <span class="whitespace-nowrap font-serif text-xs italic text-lagoon-100/80">{{ $next->nights() }} {{ Str::plural('night', $next->nights()) }}</span>
                            <span class="h-px flex-1 bg-[repeating-linear-gradient(90deg,currentColor_0_6px,transparent_6px_11px)]"></span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-lagoon-100/70">{{ $next->check_out->format('D') }}</p>
                            <p class="stay-day">{{ $next->check_out->format('j') }}</p>
                            <p class="mt-1 text-sm text-lagoon-100">{{ $next->check_out->format('M Y') }}</p>
                        </div>
                    </div>
                    <p class="sr-only">{{ $next->check_in->format('D, M j') }} to {{ $next->check_out->format('D, M j, Y') }}</p>

                    <div class="mt-6 flex items-center justify-between gap-3 border-t border-cream/10 pt-4 text-sm">
                        <span class="tracking-wider text-sand-200 tabular-nums">{{ $next->reference_code }}</span>
                        @if ($daysToGo > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-cream/10 px-3 py-1 text-lagoon-50">
                                <svg class="h-4 w-4 text-sand-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 17h16M7 17a5 5 0 0 1 10 0M12 7V5M6.3 9.3 5 8M17.7 9.3 19 8M3 21h18" /></svg>
                                {{ $daysToGo }} {{ Str::plural('day', $daysToGo) }} to go
                            </span>
                        @endif
                    </div>
                </div>
            </a>
        @endif

        {{-- Shortcuts: a quiet list on phones, three columns on wide screens. --}}
        <nav aria-label="Shortcuts" class="overflow-hidden rounded-lg border border-sand-200 bg-white/70 lg:grid lg:grid-cols-3">
            @php
                $shortcuts = [
                    [
                        'title' => 'Browse cottages',
                        'href' => route('cottages.index'),
                        'icon' => 'M3 11 12 4l9 7M5 10v10h14V10M10 20v-5h4v5',
                        'text' => 'See every cottage with its capacity, amenities, and current rates, then pick open dates on the calendar.',
                    ],
                    [
                        'title' => 'My bookings',
                        'href' => route('guest.bookings.index'),
                        'icon' => 'M3.5 7.5a2.5 2.5 0 0 1 2.5-2.5h12a2.5 2.5 0 0 1 2.5 2.5V17.5a2.5 2.5 0 0 1-2.5 2.5H6a2.5 2.5 0 0 1-2.5-2.5ZM3.5 10h17M8 3v4M16 3v4',
                        'text' => ($bookingCount ? "You have {$bookingCount} ".Str::plural('booking', $bookingCount).'.' : 'No bookings yet.').' Follow each one from pending to confirmed.',
                    ],
                    [
                        'title' => 'Payments & receipts',
                        'href' => route('guest.bookings.index'),
                        'icon' => 'M5.5 6h13A2.5 2.5 0 0 1 21 8.5v8a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-8A2.5 2.5 0 0 1 5.5 6ZM3 10h18M7 15h4',
                        'text' => config('bookease.payments.online')
                            ? 'Pay securely with GCash or Maya and download an official e-receipt after every successful payment.'
                            : 'Pay in person at the resort’s front desk and download an official e-receipt for every payment.',
                    ],
                ];
            @endphp

            @foreach ($shortcuts as $shortcut)
                <a href="{{ $shortcut['href'] }}"
                   class="group flex items-center gap-4 border-sand-200 px-4 py-4 transition-colors duration-200 hover:bg-sand-50 focus:outline-none focus-visible:bg-sand-50 [&:not(:first-child)]:border-t lg:items-start lg:px-6 lg:py-6 lg:[&:not(:first-child)]:border-l lg:[&:not(:first-child)]:border-t-0">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-b from-ocean-50 to-lagoon-50 text-lagoon-700 ring-1 ring-lagoon-200/70 transition-transform duration-300 ease-out group-hover:-rotate-6 group-active:scale-95">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $shortcut['icon'] }}" /></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-serif text-lg text-lagoon-950">{{ $shortcut['title'] }}</span>
                        <span class="mt-0.5 block text-sm leading-snug text-gray-600">{{ $shortcut['text'] }}</span>
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-lagoon-400 transition-transform duration-200 group-hover:translate-x-0.5 lg:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 14.8a.75.75 0 0 1 0-1.06L10.94 10 7.2 6.26a.75.75 0 1 1 1.06-1.06l4.27 4.27a.75.75 0 0 1 0 1.06L8.26 14.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" /></svg>
                </a>
            @endforeach
        </nav>
    </div>
</x-app-layout>
