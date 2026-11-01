{{-- Availability calendar + server-computed quote. Expects $calendar from CottageController@show. --}}
<section x-data="stayCalendar(@js($calendar))" class="card mt-8 p-4 sm:p-6" aria-labelledby="calendar-heading">
    <div class="flex items-center justify-between gap-3">
        <h2 id="calendar-heading" class="font-display text-xl font-semibold text-lagoon-950">Pick your dates</h2>
        <button type="button" x-show="checkIn" x-cloak @click="clear()" class="link-underline text-sm">Clear</button>
    </div>

    {{-- chosen dates --}}
    <div class="mt-4 grid grid-cols-2 gap-2">
        <div class="rounded-2xl border px-4 py-2.5 transition-colors duration-300"
             :class="! checkIn ? 'border-lagoon-400 bg-lagoon-50/60' : 'border-sand-200 bg-white'">
            <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-sand-600">Check-in · 2:00 PM</p>
            <p class="font-semibold text-lagoon-950" x-text="pretty(checkIn)">—</p>
        </div>
        <div class="rounded-2xl border px-4 py-2.5 transition-colors duration-300"
             :class="selectingCheckOut() ? 'border-lagoon-400 bg-lagoon-50/60' : 'border-sand-200 bg-white'">
            <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-sand-600">Check-out · 12:00 NN</p>
            <p class="font-semibold text-lagoon-950" x-text="pretty(checkOut)">—</p>
        </div>
    </div>

    {{-- month header --}}
    <div class="mt-5 flex items-center justify-between">
        <button type="button" @click="go(-1)" :disabled="! canGoBack" aria-label="Previous month"
                class="grid h-9 w-9 place-items-center rounded-full text-lagoon-800 transition hover:bg-sand-100 active:scale-90 disabled:pointer-events-none disabled:opacity-30">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6" /></svg>
        </button>
        <p class="flex items-center gap-2 font-semibold text-lagoon-950" aria-live="polite">
            <span x-text="monthLabel"></span>
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin text-lagoon-400" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20" /></svg>
        </p>
        <button type="button" @click="go(1)" :disabled="! canGoForward" aria-label="Next month"
                class="grid h-9 w-9 place-items-center rounded-full text-lagoon-800 transition hover:bg-sand-100 active:scale-90 disabled:pointer-events-none disabled:opacity-30">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" /></svg>
        </button>
    </div>

    {{-- grid --}}
    <div class="mt-2 grid grid-cols-7 text-center text-[0.7rem] font-semibold uppercase tracking-wider text-gray-400">
        @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $wd)
            <span class="py-2">{{ $wd }}</span>
        @endforeach
    </div>

    <template x-for="m in [month]" :key="m">
        <div class="cal-swap-enter grid grid-cols-7" :style="`--dir: ${direction}`" @mouseleave="hover = null">
            <template x-for="(d, i) in days" :key="m + '-' + i">
                <div>
                    <template x-if="d">
                        <button type="button" class="cal-day" :class="dayClasses(d)"
                                :disabled="isDisabled(d) && d !== checkIn"
                                :aria-pressed="d === checkIn || d === checkOut"
                                :aria-label="pretty(d) + (isBooked(d) ? ', booked' : '')"
                                @click="pick(d)" @mouseenter="hover = d" @focus="hover = d">
                            <span x-text="Number(d.slice(8))"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </template>

    {{-- legend --}}
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-lagoon-700"></span>Your stay</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background: repeating-linear-gradient(-45deg, rgb(255 173 153) 0 2px, transparent 2px 4px)"></span>Booked night</span>
        <span>You can check out on the day another guest checks in.</span>
    </div>

    <p x-show="loadError" x-cloak class="mt-3 text-sm text-coral-700">We couldn’t load availability. Please refresh the page.</p>

    {{-- quote --}}
    <div class="mt-5 border-t border-sand-200 pt-5">
        <p x-show="! checkIn" class="text-sm text-gray-600">Choose your check-in date to begin.</p>
        <p x-show="selectingCheckOut()" x-cloak class="text-sm text-gray-600">Now choose your check-out date.</p>

        <div x-show="checkIn && checkOut" x-cloak
             x-transition:enter="transition ease-out-soft duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-end justify-between gap-4">
                <div class="text-sm text-gray-600">
                    <span x-text="nights"></span> <span x-text="nights === 1 ? 'night' : 'nights'"></span>
                    <template x-if="quote"><span> × <span x-text="quote.rate"></span></span></template>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-widest text-sand-600">Total</p>
                    <p class="font-display text-3xl font-semibold text-coral-600">
                        <span x-show="quoteLoading" class="inline-block h-7 w-28 animate-pulse rounded-lg bg-sand-200 align-middle"></span>
                        <span x-show="! quoteLoading && quote" x-text="quote?.total_formatted"></span>
                    </p>
                </div>
            </div>

            <p x-show="quoteError" x-text="quoteError" class="mt-3 rounded-xl bg-coral-50 px-3 py-2 text-sm text-coral-800"></p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" class="btn-coral px-7 py-3 text-base" disabled>Reserve these dates</button>
                <span class="text-sm text-gray-500">Online reservations open soon.</span>
            </div>
        </div>
    </div>
</section>
