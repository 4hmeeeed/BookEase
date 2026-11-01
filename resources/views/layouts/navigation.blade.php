@use('App\Enums\UserRole')

@php
    $user = Auth::user();

    $links = match ($user->role) {
        UserRole::Admin => [
            ['admin.dashboard', 'Owner console', 'admin.dashboard'],
            ['admin.cottages.index', 'Cottages', 'admin.cottages.*'],
            ['staff.dashboard', 'Front desk', 'staff.dashboard'],
            ['staff.bookings.index', 'Bookings', 'staff.bookings.*'],
        ],
        UserRole::Staff => [
            ['staff.dashboard', 'Front desk', 'staff.dashboard'],
            ['staff.bookings.index', 'Bookings', 'staff.bookings.*'],
        ],
        default => [
            ['guest.dashboard', 'My stays', 'guest.dashboard'],
            ['guest.bookings.index', 'My bookings', 'guest.bookings.*'],
            ['cottages.index', 'Browse cottages', 'cottages.*'],
        ],
    };
@endphp

<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-sand-200/70 bg-cream/85 backdrop-blur-md">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-10">
                <x-brand :href="route('dashboard')" />

                <div class="hidden items-center gap-1 sm:flex">
                    @foreach ($links as [$route, $label, $pattern])
                        <x-nav-link :href="route($route)" :active="request()->routeIs($pattern)">{{ $label }}</x-nav-link>
                    @endforeach
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-3">
                <span class="badge {{ $user->isGuest() ? 'badge-checked-in' : ($user->isAdmin() ? 'badge-rejected' : 'badge-confirmed') }}">
                    <span class="badge-dot"></span>{{ $user->role->label() }}
                </span>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="group inline-flex items-center gap-2 rounded-full border border-sand-200 bg-white/70 py-1 pe-3 ps-1 text-sm font-medium text-gray-700 shadow-sm transition hover:border-lagoon-200 hover:text-lagoon-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-400">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-lagoon-700 text-xs font-semibold text-cream">
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
                    class="inline-flex items-center justify-center rounded-full p-2 text-lagoon-800 transition hover:bg-sand-100 active:scale-95 focus:outline-none sm:hidden">
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
         class="border-t border-sand-200 sm:hidden">
        <div class="space-y-1 py-3">
            @foreach ($links as [$route, $label, $pattern])
                <x-responsive-nav-link :href="route($route)" :active="request()->routeIs($pattern)">{{ $label }}</x-responsive-nav-link>
            @endforeach
        </div>

        <div class="border-t border-sand-200 pb-3 pt-4">
            <div class="px-4">
                <div class="font-medium text-gray-800">{{ $user->name }}</div>
                <div class="text-sm text-gray-500">{{ $user->email }} · {{ $user->role->label() }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">{{ __('Profile') }}</x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
