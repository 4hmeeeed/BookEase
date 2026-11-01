<x-public-layout overlay>
        {{-- ============ Hero ============ --}}
        <section class="relative overflow-hidden pb-10 pt-28 sm:pt-36">
            <div class="pointer-events-none absolute -right-32 -top-24 h-[34rem] w-[34rem] rounded-full bg-coral-200/50 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -left-40 top-40 h-96 w-96 rounded-full bg-lagoon-200/40 blur-3xl" aria-hidden="true"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:px-8">
                <div>
                    <p class="eyebrow animate-fade-up">Basey, Samar · Philippines</p>
                    <h1 class="mt-4 animate-fade-up font-display text-5xl font-semibold leading-[1.02] text-lagoon-950 [animation-delay:80ms] sm:text-6xl lg:text-7xl">
                        Your cottage by the water, <span class="relative whitespace-nowrap text-coral-500">booked<svg class="absolute -bottom-2 left-0 h-3 w-full text-coral-300" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9c40-6 90-8 196-3" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg></span> in minutes.
                    </h1>
                    <p class="mt-6 max-w-xl animate-fade-up text-lg leading-relaxed text-gray-600 [animation-delay:160ms]">
                        See which cottages are open, reserve the dates you want, and pay your down payment with GCash or Maya. No more waiting on messages or phone calls.
                    </p>

                    <div class="mt-8 flex animate-fade-up flex-wrap gap-3 [animation-delay:240ms]">
                        <a href="{{ route('cottages.index') }}" class="btn-coral px-7 py-3 text-base">Browse cottages</a>
                        <a href="#how-it-works" class="btn-ghost px-7 py-3 text-base">How it works</a>
                    </div>
                </div>

                {{-- Postcard scene: pure CSS + SVG, tilts with the pointer --}}
                <div class="animate-pop-in [animation-delay:200ms]">
                    <div x-tilt="6" class="tilt relative mx-auto aspect-[4/5] w-full max-w-md overflow-hidden rounded-4xl border-8 border-white shadow-lift sm:aspect-[5/6]">
                        <div class="absolute inset-0 bg-[linear-gradient(180deg,#ffe7c7_0%,#f7b98e_30%,#ec8f73_46%,#60acda_60%,#216c69_82%,#0b2324_100%)]"></div>
                        <div class="absolute left-[58%] top-[30%] h-24 w-24 rounded-full bg-[#fff1d6] shadow-[0_0_80px_30px_rgba(255,214,160,.6)] animate-bob"></div>

                        {{-- palm silhouette --}}
                        <svg class="absolute -left-6 bottom-[18%] h-[70%] text-lagoon-950/85" viewBox="0 0 120 260" fill="currentColor" aria-hidden="true">
                            <path d="M58 260c2-60 6-120 22-176l5 1c-15 56-19 115-21 175z"/>
                            <path d="M82 86C60 60 30 58 4 70c26-4 52 2 74 22z"/>
                            <path d="M82 86c6-30 30-48 58-52-24 12-40 30-52 56z"/>
                            <path d="M82 86c-8-28-30-44-54-50 20 12 36 30 48 54z"/>
                            <path d="M82 86c20-14 46-14 66 0-22-6-44-2-62 8z"/>
                            <path d="M82 86C66 96 48 120 42 146c10-22 26-42 44-54z"/>
                        </svg>

                        <div class="absolute inset-x-0 top-[56%] text-ocean-600/60"><x-waves /></div>
                        <div class="absolute inset-x-0 top-[66%] text-lagoon-700/70"><x-waves /></div>
                        <div class="absolute inset-x-0 bottom-0 h-[22%] bg-gradient-to-b from-sand-200 to-sand-300"></div>

                        <div class="tilt-pop absolute bottom-5 left-5 right-5 rounded-2xl bg-cream/90 p-4 shadow-soft backdrop-blur">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-widest text-sand-600">Check-in</p>
                                    <p class="font-display text-lg font-semibold text-lagoon-950">2:00 PM</p>
                                </div>
                                <svg class="h-5 w-5 text-coral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                                <div class="text-right">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-sand-600">Check-out</p>
                                    <p class="font-display text-lg font-semibold text-lagoon-950">12:00 NN</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ How it works ============ --}}
        <section id="how-it-works" class="relative bg-lagoon-900 text-cream">
            <div class="-mt-px text-cream"><x-waves class="rotate-180 !h-10 sm:!h-14" /></div>

            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div x-reveal class="max-w-2xl">
                    <p class="eyebrow text-coral-300">How it works</p>
                    <h2 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">From "is it free?" to "see you there" in three steps.</h2>
                </div>

                <ol class="mt-12 grid gap-5 md:grid-cols-3" x-reveal.stagger>
                    @foreach ([
                        ['01', 'Pick your dates', 'Browse cottages and see which dates are open. Taken dates are blocked, so two guests can never book the same cottage at once.'],
                        ['02', 'Pay the down payment', 'Pay securely with GCash or Maya. We never see or store your wallet details, and every payment is verified directly with the payment provider.'],
                        ['03', 'Arrive and unwind', 'Get your e-receipt right away, settle the balance, and check in from 2:00 PM.'],
                    ] as [$n, $title, $copy])
                        <li x-tilt class="tilt relative rounded-3xl border border-lagoon-700 bg-lagoon-800/60 p-7">
                            <span class="tilt-pop block font-display text-5xl font-semibold text-coral-300/90">{{ $n }}</span>
                            <h3 class="tilt-pop mt-4 font-display text-2xl font-semibold">{{ $title }}</h3>
                            <p class="mt-2 leading-relaxed text-lagoon-100/80">{{ $copy }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="text-cream"><x-waves class="!h-10 sm:!h-14" /></div>
        </section>

        {{-- ============ Questions / chatbot teaser ============ --}}
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div x-reveal class="card flex flex-col items-start gap-6 overflow-hidden p-8 sm:flex-row sm:items-center sm:justify-between sm:p-10">
                <div class="max-w-xl">
                    <p class="eyebrow">Questions?</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-lagoon-950">Ask about cottages, rates, and open dates any time.</h2>
                    <p class="mt-2 text-gray-600">Our resort assistant answers from up-to-date resort information. For anything else, the front desk will help you directly.</p>
                </div>
                <button type="button" x-data @click="$dispatch('open-chatbot')" class="btn-primary shrink-0 px-7 py-3">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4A1.5 1.5 0 0 1 4 14.5v-9Z" /></svg>
                    Ask the assistant
                </button>
            </div>
        </section>

</x-public-layout>
