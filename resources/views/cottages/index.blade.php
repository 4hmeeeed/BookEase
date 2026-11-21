<x-public-layout title="Cottages">
    <section class="relative overflow-hidden">

        <div class="relative mx-auto max-w-7xl px-4 pb-6 pt-10 sm:px-6 lg:px-8">
            <div class="animate-fade-up">
                <x-page-header eyebrow="Stay with us" title="Cottages, rooms & villas">
                    Every unit, rate, and capacity shown here comes straight from the resort's records.
                </x-page-header>
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('cottages.index') }}"
                  x-data="{ guests: {{ (int) ($guests ?? 0) }} }"
                  class="card mt-8 flex animate-fade-up flex-col gap-5 p-4 [animation-delay:100ms] sm:p-5 lg:flex-row lg:flex-wrap lg:items-end">

                <div class="grid grid-cols-2 gap-3 sm:w-auto"
                     x-data="{ checkIn: @js($rawCheckIn ?? ''), checkOut: @js($rawCheckOut ?? '') }">
                    {{-- Every control in this bar is h-11 so the labels line up on one baseline. --}}
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-lagoon-900">Check-in</span>
                        <input type="date" name="check_in" x-model="checkIn" min="{{ $today }}"
                               @change="if (checkOut && checkOut <= checkIn) checkOut = ''"
                               class="field h-11 !py-0" @if($stayError) aria-invalid="true" @endif>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold text-lagoon-900">Check-out</span>
                        <input type="date" name="check_out" x-model="checkOut" :min="checkIn || @js($today)"
                               class="field h-11 !py-0" @if($stayError) aria-invalid="true" @endif>
                    </label>
                </div>

                <div>
                    <span id="guests-label" class="mb-1.5 block text-sm font-semibold text-lagoon-900">Guests</span>
                    <div role="group" aria-labelledby="guests-label"
                         class="inline-flex h-11 items-center rounded-md border border-lagoon-950/15 bg-white px-1 shadow-[inset_0_1px_1px_rgb(11_35_36/0.04)] transition-colors hover:border-lagoon-950/30">
                        <button type="button" @click="guests = Math.max(0, guests - 1)" :disabled="guests === 0"
                                class="grid h-8 w-8 place-items-center rounded text-lg leading-none text-lagoon-800 transition-[background-color,transform,opacity] duration-200 hover:bg-sand-100 active:scale-90 disabled:pointer-events-none disabled:opacity-30"
                                aria-label="Fewer guests">&minus;</button>
                        <span class="w-14 text-center font-semibold tabular-nums text-lagoon-950" x-text="guests || 'Any'" aria-live="polite">{{ $guests ?: 'Any' }}</span>
                        <input type="hidden" name="guests" :value="guests" :disabled="! guests" @disabled(! $guests) value="{{ $guests }}">
                        <button type="button" @click="guests = Math.min(100, guests + 1)"
                                class="grid h-8 w-8 place-items-center rounded text-lg leading-none text-lagoon-800 transition-[background-color,transform] duration-200 hover:bg-sand-100 active:scale-90"
                                aria-label="More guests">+</button>
                    </div>
                </div>

                <fieldset class="flex-1">
                    <legend class="mb-1.5 block text-sm font-semibold text-lagoon-900">Type</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['' => 'All'] + $types as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $value }}" class="peer sr-only" @checked(($type?->value ?? '') === $value)>
                                <span class="inline-flex h-11 items-center rounded-md border border-lagoon-950/15 bg-white px-4 text-sm font-medium text-gray-700 transition-[background-color,border-color,color,transform] duration-200 ease-out-soft
                                             hover:border-lagoon-950/30 hover:text-lagoon-950 active:scale-95 peer-checked:border-lagoon-800 peer-checked:bg-lagoon-800 peer-checked:text-cream peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400 peer-focus-visible:ring-offset-2">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex gap-2">
                    <button class="btn-primary h-11">Show cottages</button>
                    @if ($guests || $type || $rawCheckIn || $rawCheckOut)
                        <a href="{{ route('cottages.index') }}" class="btn-ghost h-11">Clear</a>
                    @endif
                </div>
            </form>

            @if ($stayError)
                <p class="mt-4 animate-fade-up rounded-2xl bg-coral-50 px-4 py-3 text-sm font-medium text-coral-800" role="alert">{{ $stayError }}</p>
            @elseif ($checkIn)
                <p class="mt-4 text-sm text-gray-600">
                    Showing units free from <strong class="text-lagoon-900">{{ $checkIn->format('D, M j') }}</strong> (2:00 PM)
                    to <strong class="text-lagoon-900">{{ $checkOut->format('D, M j') }}</strong> (12:00 NN)
                    · {{ $nights }} {{ Str::plural('night', $nights) }}
                </p>
            @else
                <p class="mt-4 text-sm text-gray-500">Add your dates to see only the units that are free, with the total for your stay.</p>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        @if ($cottages->isEmpty())
            <div class="card mx-auto mt-6 max-w-lg animate-pop-in p-10 text-center">
                <p class="font-serif text-2xl font-semibold text-lagoon-950">No units match</p>
                <p class="mt-2 text-gray-600">
                    @if ($checkIn)
                        Every unit that fits your search is already booked for those dates.
                    @elseif ($guests)
                        None of our listed units fit {{ $guests }} {{ Str::plural('guest', $guests) }}{{ $type ? ' as a '.Str::lower($type->label()) : '' }}.
                    @else
                        Nothing is listed here right now.
                    @endif
                    Try a different filter, or contact the resort and we'll help you find a fit.
                </p>
                <a href="{{ route('cottages.index') }}" class="btn-ghost mt-6">See all units</a>
            </div>
        @else
            {{-- h2 so the card titles (h3) sit under a real section heading --}}
            <h2 class="mb-4 text-sm font-medium text-gray-500">
                {{ $cottages->count() }} {{ Str::plural('unit', $cottages->count()) }}{{ $checkIn ? ' available' : '' }} · sorted by nightly rate
            </h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
                @foreach ($cottages as $cottage)
                    <x-cottage-card :cottage="$cottage"
                                    :total="$quotes[$cottage->id] ?? null"
                                    :nights="$nights"
                                    :check-in="$checkIn?->toDateString()"
                                    :check-out="$checkOut?->toDateString()" />
                @endforeach
            </div>
        @endif
    </section>
</x-public-layout>
