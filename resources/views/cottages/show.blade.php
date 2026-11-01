<x-public-layout :title="$cottage->name">
    <section class="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 lg:px-8">
        <a href="{{ route('cottages.index') }}" class="link-underline text-sm">&larr; All cottages</a>

        <div class="mt-6 grid gap-10 lg:grid-cols-[1.25fr_1fr]">
            <div class="lg:sticky lg:top-24 lg:self-start">
                <div x-tilt="3" class="tilt group overflow-hidden rounded-lg shadow-plate">
                    <x-cottage-image :cottage="$cottage" morph class="aspect-[4/3]" />
                </div>
            </div>

            <div class="animate-fade-up [animation-delay:160ms]">
                <span class="badge badge-confirmed"><span class="badge-dot"></span>{{ $cottage->type->label() }}</span>
                <h1 class="mt-4 font-display text-4xl leading-[1.05] text-lagoon-950 sm:text-5xl">{{ $cottage->name }}</h1>

                <dl class="mt-7 grid grid-cols-2 divide-x divide-lagoon-950/10 border-y border-lagoon-950/10">
                    <div class="py-4 pr-4">
                        <dt class="sounding text-gray-500">Nightly rate</dt>
                        <dd class="tabular mt-1 text-3xl font-semibold tracking-tight text-lagoon-950">{{ $cottage->formatted_price }}</dd>
                    </div>
                    <div class="py-4 pl-5">
                        <dt class="sounding text-gray-500">Sleeps up to</dt>
                        <dd class="tabular mt-1 text-3xl font-semibold tracking-tight text-lagoon-950">{{ $cottage->capacity }} <span class="text-base font-medium text-gray-500">{{ Str::plural('guest', $cottage->capacity) }}</span></dd>
                    </div>
                </dl>

                @if ($cottage->description)
                    <div class="mt-6 max-w-prose whitespace-pre-line leading-relaxed text-gray-700">{{ $cottage->description }}</div>
                @endif

                @include('cottages._calendar')
            </div>
        </div>
    </section>

    @if ($others->isNotEmpty())
        <section class="border-t border-lagoon-950/10 bg-cream-deep/70">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <h2 x-reveal class="font-display text-4xl text-lagoon-950">You might also like</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger.rise>
                    @foreach ($others as $other)
                        <x-cottage-card :cottage="$other" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-public-layout>
