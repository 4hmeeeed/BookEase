{{-- Guests only: staff and the owner get the console layout (layouts.console). --}}
@php
    $user = Auth::user();

    $links = [
        ['guest.dashboard', 'My stays', 'guest.dashboard'],
        ['guest.bookings.index', 'My bookings', 'guest.bookings.*'],
        ['cottages.index', 'Browse cottages', 'cottages.*'],
    ];
@endphp

<nav x-data="{ open: false }" class="sticky top-0 z-40 print:hidden border-b border-lagoon-950/10 bg-cream/90 backdrop-blur-md">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-10">
                <x-brand :href="route('dashboard')" />

                <div class="hidden items-center gap-1 lg:flex">
                    @foreach ($links as [$route, $label, $pattern])
                        <x-nav-link :href="route($route)" :active="request()->routeIs($pattern)">{{ $label }}</x-nav-link>
                    @endforeach
                </div>
            </div>

            <div class="hidden items-center gap-3 lg:flex">
                <span class="badge badge-checked-in">
                    <span class="badge-dot"></span>{{ $user->role->label() }}
                </span>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="group inline-flex items-center gap-2 rounded-md border border-lagoon-950/10 bg-white py-1 pe-2.5 ps-1 text-sm font-medium text-gray-700 shadow-sm transition hover:border-lagoon-200 hover:text-lagoon-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400">
                            <span class="grid h-7 w-7 place-items-center rounded bg-lagoon-900 text-xs font-semibold text-cream">
                                {{ Str::of($user->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->take(2)->implode('') }}
                            </span>
                            <span class="max-w-[10rem] truncate">{{ $user->name }}</span>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Hamburger --}}
            <button @click="open = ! open" :aria-expanded="open" aria-label="Toggle menu"
                    class="inline-flex items-center justify-center rounded-md p-2 text-lagoon-800 transition hover:bg-sand-100 active:scale-95 focus:outline-none lg:hidden">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{ 'hidden': open }" stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h10M4 17h16" />
                    <path :class="{ 'hidden': ! open }" class="hidden" stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out-soft duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="border-t border-lagoon-950/10 lg:hidden">
        {{-- Big serif destinations; a small swell marks the page you're on. --}}
        <ul class="px-4 py-4">
            @foreach ($links as [$route, $label, $pattern])
                @php $active = request()->routeIs($pattern); @endphp
                <li>
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                       class="group flex items-center justify-between rounded-md px-2 py-2.5 font-serif text-2xl transition-colors duration-200 {{ $active ? 'text-lagoon-950' : 'text-lagoon-900/60 hover:text-lagoon-950' }}">
                        <span>{{ $label }}</span>
                        @if ($active)
                            <svg class="h-3 w-10 text-lagoon-400" viewBox="0 0 40 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M1 6c4.5-5 9-5 13 0s8.5 5 13 0 8.5-5 12 0" /></svg>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mx-4 mb-4 flex items-center gap-3 rounded-lg border border-sand-200 bg-sand-50 p-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-b from-ocean-100 to-lagoon-100 text-sm font-semibold text-lagoon-900 ring-1 ring-lagoon-200/70">
                {{ Str::of($user->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->take(2)->implode('') }}
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate font-medium text-lagoon-950">{{ $user->name }}</div>
                <div class="truncate text-sm text-gray-500">{{ $user->email }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 px-4 pb-4">
            <a href="{{ route('profile.edit') }}" class="btn-ghost w-full justify-center">{{ __('Profile') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-ghost w-full justify-center">{{ __('Log Out') }}</button>
            </form>
        </div>
    </div>
</nav>
