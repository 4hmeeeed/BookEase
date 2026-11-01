<x-public-layout :title="$cottage->name">
    <section class="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 lg:px-8">
        <a href="{{ route('cottages.index') }}" class="link-underline text-sm">&larr; All cottages</a>

        <div class="mt-6 grid gap-10 lg:grid-cols-[1.25fr_1fr]">
            <div class="animate-pop-in">
                <div x-tilt="4" class="tilt group overflow-hidden rounded-4xl border-8 border-white shadow-lift">
                    <x-cottage-image :cottage="$cottage" class="aspect-[4/3]" />
                </div>
            </div>

            <div class="animate-fade-up [animation-delay:120ms]">
                <span class="badge badge-confirmed"><span class="badge-dot"></span>{{ $cottage->type->label() }}</span>
                <h1 class="mt-4 font-display text-4xl font-semibold leading-tight text-lagoon-950 sm:text-5xl">{{ $cottage->name }}</h1>

                <dl class="mt-6 grid grid-cols-2 gap-4">
                    <div class="card p-4">
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sand-600">Nightly rate</dt>
                        <dd class="mt-1 font-display text-3xl font-semibold text-coral-600">{{ $cottage->formatted_price }}</dd>
                    </div>
                    <div class="card p-4">
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sand-600">Sleeps up to</dt>
                        <dd class="mt-1 font-display text-3xl font-semibold text-lagoon-900">{{ $cottage->capacity }} <span class="text-base font-sans font-medium text-gray-500">{{ Str::plural('guest', $cottage->capacity) }}</span></dd>
                    </div>
                </dl>

                @if ($cottage->description)
                    <div class="mt-6 whitespace-pre-line leading-relaxed text-gray-700">{{ $cottage->description }}</div>
                @endif

                @include('cottages._calendar')
            </div>
        </div>
    </section>

    @if ($others->isNotEmpty())
        <section class="border-t border-sand-200 bg-cream-deep/60">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <h2 x-reveal class="font-display text-3xl font-semibold text-lagoon-950">You might also like</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
                    @foreach ($others as $other)
                        <x-cottage-card :cottage="$other" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-public-layout>
