<x-public-layout title="Cottages">
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-coral-200/40 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-4 pb-6 pt-10 sm:px-6 lg:px-8">
            <div class="animate-fade-up">
                <x-page-header eyebrow="Stay with us" title="Cottages, rooms & villas">
                    Every unit, rate, and capacity shown here comes straight from the resort's records.
                </x-page-header>
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('cottages.index') }}"
                  x-data="{ guests: {{ (int) ($guests ?? 0) }} }"
                  class="card mt-8 flex animate-fade-up flex-col gap-5 p-4 [animation-delay:100ms] sm:p-5 lg:flex-row lg:items-end">

                <div>
                    <span class="mb-1.5 block text-sm font-semibold text-lagoon-900">Guests</span>
                    <div class="inline-flex items-center rounded-full border border-sand-300 bg-white p-1 shadow-sm">
                        <button type="button" @click="guests = Math.max(0, guests - 1)" class="grid h-9 w-9 place-items-center rounded-full text-lagoon-800 transition hover:bg-sand-100 active:scale-90" aria-label="Fewer guests">&minus;</button>
                        <span class="w-14 text-center font-semibold text-lagoon-950 tabular-nums" x-text="guests || 'Any'" aria-live="polite">{{ $guests ?: 'Any' }}</span>
                        <input type="hidden" name="guests" :value="guests" :disabled="! guests" @disabled(! $guests) value="{{ $guests }}">
                        <button type="button" @click="guests = Math.min(100, guests + 1)" class="grid h-9 w-9 place-items-center rounded-full text-lagoon-800 transition hover:bg-sand-100 active:scale-90" aria-label="More guests">+</button>
                    </div>
                </div>

                <fieldset class="flex-1">
                    <legend class="mb-1.5 block text-sm font-semibold text-lagoon-900">Type</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['' => 'All'] + $types as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $value }}" class="peer sr-only" @checked(($type?->value ?? '') === $value)>
                                <span class="inline-flex rounded-full border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-all duration-200 ease-out-soft
                                             hover:border-lagoon-300 active:scale-95 peer-checked:border-lagoon-700 peer-checked:bg-lagoon-700 peer-checked:text-cream peer-checked:shadow-lagoon-glow peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex gap-2">
                    <button class="btn-primary">Show cottages</button>
                    @if ($guests || $type)
                        <a href="{{ route('cottages.index') }}" class="btn-ghost">Clear</a>
                    @endif
                </div>
            </form>

            <p class="mt-4 text-sm text-gray-500">
                Picking stay dates is coming soon. For now, filter by party size and type.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        @if ($cottages->isEmpty())
            <div class="card mx-auto mt-6 max-w-lg animate-pop-in p-10 text-center">
                <p class="font-display text-2xl font-semibold text-lagoon-950">No units match</p>
                <p class="mt-2 text-gray-600">
                    @if ($guests)
                        None of our listed units fit {{ $guests }} {{ Str::plural('guest', $guests) }}{{ $type ? ' as a '.Str::lower($type->label()) : '' }}.
                    @else
                        Nothing is listed here right now.
                    @endif
                    Try a different filter, or contact the resort and we'll help you find a fit.
                </p>
                <a href="{{ route('cottages.index') }}" class="btn-ghost mt-6">See all units</a>
            </div>
        @else
            <p class="mb-4 text-sm font-medium text-gray-500">{{ $cottages->count() }} {{ Str::plural('unit', $cottages->count()) }} · sorted by nightly rate</p>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
                @foreach ($cottages as $cottage)
                    <x-cottage-card :cottage="$cottage" />
                @endforeach
            </div>
        @endif
    </section>
</x-public-layout>
