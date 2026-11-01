@php
    $today = now()->toDateString();
    $pins = [[24, 30], [63, 21], [76, 57], [43, 68], [16, 74]];
    $questions = [
        'What cottages do you have?',
        'What time is check-in and check-out?',
        'Is anything free this weekend?',
        'How do I pay the down payment?',
    ];
@endphp

<x-public-layout overlay>
    {{-- =====================================================================
         1 · Hero — a moving shore laid down like a chart plate.
         Stock placeholder footage (Pexels) — see public/videos/CREDITS.md.
         ===================================================================== --}}
    <section x-parallax class="hero-stage relative isolate min-h-[100svh] overflow-hidden bg-[#e6d9cf]">
        <div class="hero-plate absolute inset-0 -z-10">
            <div class="depth absolute -inset-8" style="--d: -14">
                <video class="h-full w-full object-cover motion-reduce:hidden" autoplay muted loop playsinline preload="metadata"
                       poster="{{ asset('videos/shore-dusk-poster.webp') }}" aria-hidden="true">
                    <source src="{{ asset('videos/shore-dusk.webm') }}" type="video/webm">
                    <source src="{{ asset('videos/shore-dusk.mp4') }}" type="video/mp4">
                </video>
                <img src="{{ asset('videos/shore-dusk-poster.webp') }}" alt="" class="hidden h-full w-full object-cover motion-reduce:block">
            </div>
            {{-- keep the ink legible on the pale sky, deepen the foam toward the dock --}}
            <div class="absolute inset-0 bg-[linear-gradient(180deg,rgb(250_247_241/0.78)_0%,rgb(250_247_241/0.42)_44%,rgb(250_247_241/0.12)_58%,rgb(11_35_36/0.5)_100%)]"></div>
        </div>

        {{-- Chart furniture: neatline, graticule that draws itself, coordinates --}}
        <div class="neatline text-lagoon-950/25" aria-hidden="true"></div>
        <div class="depth pointer-events-none absolute inset-0" style="--d: 6" aria-hidden="true">
            <span class="grid-line absolute inset-x-0 top-[47.5%] h-px origin-left bg-lagoon-950/15" style="--delay: 500ms"></span>
            <span class="grid-line-v absolute inset-y-0 left-[33.333%] w-px origin-top bg-lagoon-950/15" style="--delay: 650ms"></span>
            <span class="grid-line-v absolute inset-y-0 left-[66.666%] w-px origin-top bg-lagoon-950/15" style="--delay: 800ms"></span>
            <span class="sounding fade-late absolute left-[33.333%] top-28 ml-2 text-lagoon-950/55 sm:top-32" style="--delay: 1300ms">125&deg;04&prime;E</span>
            <span class="sounding fade-late absolute right-6 top-[47.5%] mt-2 text-lagoon-950/55 sm:right-9" style="--delay: 1400ms">11&deg;17&prime;N</span>
        </div>

        <div class="relative mx-auto flex min-h-[100svh] max-w-7xl flex-col px-6 pb-8 pt-32 sm:px-10 sm:pt-40 lg:px-12">
            <div class="depth grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end" style="--d: 10">
                <h1 class="font-display text-[clamp(3.1rem,8.6vw,6rem)] leading-[0.98] tracking-[-0.025em] text-lagoon-950 [perspective:900px]">
                    <span class="line-mask"><span style="--i: 0">Your cottage</span></span>
                    <span class="line-mask"><span style="--i: 1">by the water,</span></span>
                    <span class="line-mask"><span style="--i: 2">booked in minutes.</span></span>
                </h1>
                <p class="fade-late max-w-md text-[1.05rem] leading-relaxed text-lagoon-950/85 lg:mb-3 lg:border-l lg:border-lagoon-950/20 lg:pl-5" style="--delay: 800ms">
                    See which cottages are open, reserve the dates you want, and pay your down payment with GCash or Maya. No more waiting on messages or phone calls.
                </p>
            </div>

            {{-- Booking dock: real search, lifts out of the page in 3D --}}
            <div class="mt-auto pt-14">
                <form method="GET" action="{{ route('cottages.index') }}"
                      class="dock depth grid gap-px overflow-hidden rounded-lg border border-lagoon-950/10 bg-lagoon-950/10 shadow-plate sm:grid-cols-2 lg:max-w-5xl lg:grid-cols-[1fr_1fr_0.8fr_auto]" style="--d: 18"
                      x-data="{ checkIn: '', checkOut: '' }">
                    <label class="group block bg-cream px-5 py-3.5 transition-colors focus-within:bg-white hover:bg-white">
                        <span class="sounding text-lagoon-700">Check-in · 2:00 PM</span>
                        <input type="date" name="check_in" min="{{ $today }}" x-model="checkIn"
                               @change="if (checkOut && checkOut <= checkIn) checkOut = ''"
                               class="mt-1 block w-full border-0 bg-transparent p-0 text-base font-semibold text-lagoon-950 focus:ring-0">
                    </label>
                    <label class="group block bg-cream px-5 py-3.5 transition-colors focus-within:bg-white hover:bg-white">
                        <span class="sounding text-lagoon-700">Check-out · 12:00 NN</span>
                        <input type="date" name="check_out" :min="checkIn || '{{ $today }}'" x-model="checkOut"
                               class="mt-1 block w-full border-0 bg-transparent p-0 text-base font-semibold text-lagoon-950 focus:ring-0">
                    </label>
                    <label class="group block bg-cream px-5 py-3.5 transition-colors focus-within:bg-white hover:bg-white">
                        <span class="sounding text-lagoon-700">Guests</span>
                        <select name="guests" class="mt-1 block w-full border-0 bg-transparent p-0 pr-8 text-base font-semibold text-lagoon-950 focus:ring-0">
                            <option value="">Any</option>
                            @for ($n = 1; $n <= max(1, (int) $maxGuests); $n++)
                                <option value="{{ $n }}">{{ $n }} {{ Str::plural('guest', $n) }}</option>
                            @endfor
                        </select>
                    </label>
                    <button type="submit" class="group flex items-center justify-center gap-2 bg-lagoon-950 px-7 py-4 text-sm font-semibold text-cream transition-colors hover:bg-lagoon-800 focus:outline-none focus-visible:bg-lagoon-800 active:bg-lagoon-900">
                        See open cottages <span class="arrow">&rarr;</span>
                    </button>
                </form>

                <div class="fade-late mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 pr-16 text-sm text-cream sm:pr-0" style="--delay: 1300ms">
                    @if ($unitCount)
                        <span><span class="tabular font-semibold">{{ $unitCount }}</span> {{ Str::plural('unit', $unitCount) }} listed</span>
                        <span class="h-3 w-px bg-cream/40" aria-hidden="true"></span>
                        <span>Rates from <span class="tabular font-semibold">{{ $fromRate }}</span> a night</span>
                        <span class="h-3 w-px bg-cream/40" aria-hidden="true"></span>
                    @endif
                    <span>Down payment with GCash or Maya</span>
                </div>
            </div>
        </div>
    </section>

    {{-- =====================================================================
         2 · Cottages — live units and rates, flipping up in 3D.
         ===================================================================== --}}
    <section id="cottages" class="relative bg-cream py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 sm:px-10 lg:px-12">
            <div x-reveal class="flex flex-col gap-6 border-b border-lagoon-950/10 pb-8 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <h2 class="font-display text-5xl leading-[1.02] text-lagoon-950 sm:text-6xl">Cottages on the shore</h2>
                    <p class="mt-4 text-gray-600">Every unit, rate, and capacity shown here comes straight from the resort's records.</p>
                </div>
                <a href="{{ route('cottages.index') }}" class="btn-ghost shrink-0 self-start md:self-auto">
                    See all {{ $unitCount ?: '' }} {{ Str::plural('cottage', max(1, $unitCount)) }} <span class="arrow">&rarr;</span>
                </a>
            </div>

            @if ($cottages->isEmpty())
                <p class="mt-10 text-gray-600">Cottages are being listed. For now, please contact the resort directly.</p>
            @else
                <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3" x-reveal.stagger.rise>
                    @foreach ($cottages as $cottage)
                        <x-cottage-card :cottage="$cottage" :size="$loop->first && $cottages->count() >= 3 ? 'feature' : 'base'"
                                        @class(['lg:row-span-2' => $loop->first && $cottages->count() >= 3]) />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- =====================================================================
         3 · The bay in layers — a scroll-driven 3D chart plate.
         Pins are the live units; positions are illustrative, not a site map.
         ===================================================================== --}}
    <section class="relative overflow-hidden bg-lagoon-950 py-24 text-cream sm:py-32">
        <div class="graticule pointer-events-none absolute inset-0 text-cream/[0.04]" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-6 sm:px-10 lg:grid-cols-[0.85fr_1.15fr] lg:px-12">
            <div x-reveal>
                <h2 class="font-display text-5xl leading-[1.02] sm:text-6xl">Every cottage, on one live chart.</h2>
                <p class="mt-6 max-w-md leading-relaxed text-cream/70">
                    Rates and sizes come straight from the front desk's records. Each unit keeps its own calendar, and taken nights are blocked, so two guests can never book the same cottage at once.
                </p>

                @if ($unitCount)
                    <dl class="mt-10 grid max-w-md grid-cols-3 divide-x divide-cream/10 border-y border-cream/10">
                        <div class="py-5 pr-4">
                            <dt class="sounding text-cream/50">Units</dt>
                            <dd class="tabular mt-2 font-display text-4xl">{{ $unitCount }}</dd>
                        </div>
                        <div class="px-4 py-5">
                            <dt class="sounding text-cream/50">From</dt>
                            <dd class="tabular mt-2 text-2xl font-semibold leading-[2.5rem] tracking-tight">{{ $fromRate }}</dd>
                        </div>
                        <div class="py-5 pl-4">
                            <dt class="sounding text-cream/50">Groups to</dt>
                            <dd class="tabular mt-2 font-display text-4xl">{{ $maxGuests }}</dd>
                        </div>
                    </dl>
                @endif

                <a href="{{ route('cottages.index') }}" class="btn-light mt-10">Check availability <span class="arrow">&rarr;</span></a>
            </div>

            <div x-bay class="bay relative mx-auto w-full max-w-[38rem]" aria-label="Illustrative chart of the resort's units">
                <div class="bay-plate aspect-square w-full">
                    {{-- the plate: aerial photo (stock placeholder, Unsplash) + graticule --}}
                    <div class="bay-layer overflow-hidden rounded-lg shadow-[0_60px_120px_-30px_rgb(0_0_0/0.7)]" style="--z: 0">
                        <img src="{{ asset('images/stock/bay-aerial.webp') }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        <div class="graticule absolute inset-0 text-cream/25 [background-size:12.5%_12.5%]"></div>
                        <div class="absolute inset-0 ring-1 ring-inset ring-cream/30"></div>
                        @foreach ($cottages as $cottage)
                            @php [$x, $y] = $pins[$loop->index] ?? [50, 50]; @endphp
                            <span class="absolute h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full border border-coral-300 bg-coral-500/25" style="left: {{ $x }}%; top: {{ $y }}%"></span>
                        @endforeach
                    </div>

                    {{-- depth contours floating above the plate --}}
                    <svg class="bay-layer h-full w-full text-cream/35" style="--z: 46" viewBox="0 0 100 100" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M8 62c6-14 22-20 34-16s20 14 34 10 18-14 20-8-4 22-18 28-34 6-48 2S2 76 8 62Z" stroke-width=".35" />
                        <path d="M30 18c10-8 28-6 34 4s-2 20-14 22-26-4-26-12 2-10 6-14Z" stroke-width=".35" />
                    </svg>
                    <svg class="bay-layer h-full w-full text-cream/25" style="--z: 92" viewBox="0 0 100 100" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M18 64c6-8 16-10 24-7s14 9 24 6 12-8 14-4-4 12-14 15-24 3-34 0-18-4-14-10Z" stroke-width=".3" stroke-dasharray="1.2 1.2" />
                        <path d="M38 22c6-4 16-3 19 3s-1 11-8 12-14-2-14-7 1-6 3-8Z" stroke-width=".3" stroke-dasharray="1.2 1.2" />
                    </svg>

                    {{-- live pins standing up off the plate --}}
                    @foreach ($cottages as $cottage)
                        @php [$x, $y] = $pins[$loop->index] ?? [50, 50]; @endphp
                        <a href="{{ route('cottages.show', $cottage) }}" class="bay-pin group" style="left: {{ $x }}%; top: {{ $y }}%; --z: {{ 70 + $loop->index * 18 }}">
                            <span class="bay-pin-card absolute bottom-0 left-0 block" style="translate: -50% 0">
                                <span class="block whitespace-nowrap rounded border border-cream/20 bg-lagoon-950/90 px-2.5 py-1.5 text-left shadow-lift backdrop-blur-sm transition-colors group-hover:border-coral-300 group-focus-visible:border-coral-300">
                                    <span class="block max-w-[8.5rem] truncate font-serif text-[0.72rem] leading-tight text-cream sm:max-w-[11rem] sm:text-[0.8rem]">{{ $cottage->name }}</span>
                                    <span class="tabular block text-[0.7rem] font-semibold text-coral-300">{{ $cottage->formatted_price }}<span class="font-normal text-cream/60"> / night</span></span>
                                </span>
                                <span class="mx-auto block h-10 w-px bg-gradient-to-b from-cream/70 to-transparent"></span>
                                <span class="mx-auto -mt-1 block h-2 w-2 rounded-full bg-coral-400 ring-2 ring-lagoon-950"></span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <p class="sounding mt-6 text-center text-cream/35">Illustrative chart · pins show live units and rates</p>
            </div>
        </div>
    </section>

    {{-- =====================================================================
         4 · How booking works — the noon changeover, drawn as a tide line.
         ===================================================================== --}}
    <section id="changeover" class="relative scroll-mt-10 bg-cream py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 sm:px-10 lg:px-12">
            <h2 x-reveal class="max-w-3xl font-display text-5xl leading-[1.02] text-lagoon-950 sm:text-6xl">From "is it free?" to "see you there" in three steps.</h2>

            {{-- Changeover strip: 8 AM → 6 PM on a changeover day --}}
            <figure x-reveal class="changeover mt-14 rounded-lg border border-lagoon-950/10 bg-white p-6 shadow-soft sm:p-8">
                <div class="relative h-24">
                    {{-- hour ticks --}}
                    <div class="absolute inset-x-0 bottom-0 flex justify-between" aria-hidden="true">
                        @foreach (['8 AM', '10', '12 NN', '2 PM', '4', '6 PM'] as $hour)
                            <span class="relative flex flex-col items-center">
                                <span class="mb-1.5 h-2 w-px bg-lagoon-950/30"></span>
                                <span class="sounding text-gray-500">{{ $hour }}</span>
                            </span>
                        @endforeach
                    </div>
                    {{-- one stay ends at noon (40% of 8→18), the next begins at 2 PM (60%) --}}
                    <div class="absolute inset-x-0 top-3 h-px bg-lagoon-950/10"></div>
                    <div class="bar bar-out absolute left-0 top-0 h-7 w-[40%] rounded-l-sm bg-lagoon-800"></div>
                    <div class="bar bar-in absolute right-0 top-0 h-7 w-[40%] rounded-r-sm bg-ocean-600"></div>
                    <div class="absolute left-[40%] top-0 h-7 w-[20%] bg-[repeating-linear-gradient(-45deg,rgb(11_35_36/0.12)_0_1px,transparent_1px_5px)]"></div>
                    <span class="absolute left-[40%] top-9 -translate-x-full pr-2 text-xs font-semibold text-lagoon-900">Check-out 12:00 NN</span>
                    <span class="absolute left-[60%] top-9 pl-2 text-xs font-semibold text-ocean-800">Check-in 2:00 PM</span>
                </div>
                <figcaption class="mt-6 max-w-2xl text-sm leading-relaxed text-gray-600">
                    A stay holds its nights from check-in up to the morning of check-out, so one guest can leave at noon and the next can arrive at 2:00 PM the same day. Taken nights are blocked for everyone else.
                </figcaption>
            </figure>

            {{-- The three steps, set like a tide table --}}
            <ol class="mt-14 border-t border-lagoon-950/15" x-reveal.stagger>
                @foreach ([
                    ['Any time', 'Pick your dates', 'Browse cottages and see which dates are open. Taken dates are blocked, so two guests can never book the same cottage at once.'],
                    ['GCash · Maya', 'Pay the down payment', 'Pay securely with GCash or Maya. We never see or store your wallet details, and every payment is verified directly with the payment provider.'],
                    ['From 2:00 PM', 'Arrive and unwind', 'Get your e-receipt right away, settle the balance, and check in from 2:00 PM.'],
                ] as [$when, $title, $copy])
                    <li class="group grid gap-2 border-b border-lagoon-950/15 py-7 transition-colors duration-300 hover:bg-white/70 sm:grid-cols-[11rem_1fr_1.4fr] sm:gap-8 sm:px-4">
                        <span class="sounding pt-2 text-coral-600">{{ $when }}</span>
                        <h3 class="font-display text-3xl leading-tight text-lagoon-950 transition-transform duration-500 ease-expo group-hover:translate-x-1.5">{{ $title }}</h3>
                        <p class="max-w-prose leading-relaxed text-gray-600 sm:pt-1.5">{{ $copy }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- =====================================================================
         5 · Ask — the assistant, surfacing out of the dark water.
         ===================================================================== --}}
    <section class="relative isolate overflow-hidden bg-lagoon-950 py-24 text-cream sm:py-32">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            {{-- Stock placeholder (Unsplash) — see public/images/stock/CREDITS.md --}}
            <img src="{{ asset('images/stock/dusk-boats.webp') }}" alt="" loading="lazy" decoding="async" class="tide h-full w-full object-cover opacity-45">
            <div class="absolute inset-0 bg-gradient-to-r from-lagoon-950 via-lagoon-950/85 to-lagoon-950/30"></div>
        </div>

        <div class="mx-auto grid max-w-7xl gap-14 px-6 sm:px-10 lg:grid-cols-2 lg:px-12">
            <div x-reveal>
                <h2 class="font-display text-5xl leading-[1.02] sm:text-6xl">Ask about cottages, rates, and open dates any time.</h2>
                <p class="mt-6 max-w-md leading-relaxed text-cream/70">Our resort assistant answers from up-to-date resort information. For anything else, the front desk will help you directly.</p>
                <button type="button" x-data @click="$dispatch('open-chatbot')" class="btn-light mt-10">
                    Ask the assistant <span class="arrow">&rarr;</span>
                </button>
            </div>

            <div class="self-end" x-data>
                <p class="sounding text-cream/45">Try asking</p>
                <ul class="mt-4 border-t border-cream/15" x-reveal.stagger>
                    @foreach ($questions as $question)
                        <li>
                            <button type="button" @click="$dispatch('open-chatbot', { question: @js($question) })"
                                    class="group flex w-full items-center justify-between gap-6 border-b border-cream/15 py-5 text-left font-serif text-xl text-cream/85 transition-colors hover:text-cream focus:outline-none focus-visible:text-cream">
                                <span class="transition-transform duration-500 ease-expo group-hover:translate-x-2">{{ $question }}</span>
                                <span class="arrow text-coral-300">&rarr;</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-5 text-xs leading-relaxed text-cream/50">Answers come only from the resort's live records. The assistant can't make or change bookings.</p>
            </div>
        </div>
    </section>

    {{-- =====================================================================
         6 · Close
         ===================================================================== --}}
    <section class="relative isolate overflow-hidden">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            {{-- Stock placeholder (Unsplash) — see public/images/stock/CREDITS.md --}}
            <img src="{{ asset('images/stock/palms-sunset.webp') }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover object-[50%_70%]">
            <div class="absolute inset-0 bg-gradient-to-t from-lagoon-950/80 via-lagoon-950/30 to-lagoon-950/10"></div>
        </div>
        <div x-reveal class="mx-auto flex min-h-[26rem] max-w-7xl flex-col justify-end px-6 py-16 text-cream sm:min-h-[32rem] sm:px-10 lg:px-12">
            <h2 class="max-w-2xl font-display text-5xl leading-[1.02] sm:text-6xl">Your dates, held the moment you book.</h2>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('cottages.index') }}" class="btn-light">Browse cottages <span class="arrow">&rarr;</span></a>
                @guest
                    <a href="{{ route('register') }}" class="btn border border-cream/40 text-cream hover:bg-cream/10">Create an account</a>
                @endguest
            </div>
        </div>
    </section>
</x-public-layout>
