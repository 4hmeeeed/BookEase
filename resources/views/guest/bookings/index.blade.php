<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="My stays" title="My bookings">
            Track each reservation from request to check-out.
            <x-slot name="actions">
                <a href="{{ route('cottages.index') }}" class="btn-coral">{{ $current->isEmpty() && $past->isEmpty() ? 'Book a stay' : 'Book another stay' }}</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-12 px-4 py-8 sm:px-6 lg:px-8">
        <section>
            <h2 class="font-serif text-2xl font-semibold text-lagoon-950">Upcoming & current</h2>

            @forelse ($current as $booking)
                @if ($loop->first)<ul class="mt-4 grid gap-5 md:grid-cols-2" x-reveal.stagger>@endif
                    <li>@include('guest.bookings._card', ['booking' => $booking])</li>
                @if ($loop->last)</ul>@endif
            @empty
                <div class="card mt-4 p-10 text-center">
                    <p class="font-serif text-xl font-semibold text-lagoon-950">No upcoming stays yet</p>
                    <p class="mt-1 text-gray-600">Find a cottage and pick your dates. It only takes a minute.</p>
                    <a href="{{ route('cottages.index') }}" class="btn-primary mt-5">Browse cottages</a>
                </div>
            @endforelse
        </section>

        @if ($past->isNotEmpty())
            <section>
                <h2 class="font-serif text-2xl font-semibold text-lagoon-950">Past & cancelled</h2>
                <ul class="mt-4 grid gap-5 md:grid-cols-2" x-reveal.stagger>
                    @foreach ($past as $booking)
                        <li class="opacity-80 transition hover:opacity-100">@include('guest.bookings._card', ['booking' => $booking])</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-app-layout>
