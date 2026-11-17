{{-- Desktop sidebar (lg+), on chart ink with a faint graticule. Phones use console-tabbar. --}}
@php $user = auth()->user(); @endphp

<aside data-console-sidebar class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-lagoon-950 text-cream/80 print:hidden lg:flex">
    <div class="graticule pointer-events-none absolute inset-0 text-cream opacity-[0.06]" aria-hidden="true"></div>

    <div class="relative flex h-16 shrink-0 items-center border-b border-cream/10 px-5">
        <x-brand :href="route($console['home'])" light />
    </div>

    <nav class="relative flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Console">
        @foreach ($console['groups'] as $group)
            <div>
                @if ($group['label'])
                    <p class="px-3 pb-2 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-cream/45">{{ $group['label'] }}</p>
                @endif

                <ul class="space-y-0.5">
                    @foreach ($group['links'] as $link)
                        @php $active = request()->routeIs($link['pattern']); @endphp
                        <li>
                            <a href="{{ route($link['route']) }}"
                               @class(['console-link', 'is-active' => $active])
                               @if ($active) aria-current="page" @endif
                               @if ($link['route'] === 'staff.bookings.index') data-pending-count="{{ $console['pending'] }}" @endif>
                                <x-console-icon :name="$link['icon']" class="h-[1.125rem] w-[1.125rem] shrink-0" />
                                <span class="flex-1 truncate">{{ $link['label'] }}</span>
                                @if ($link['badge'])
                                    <span class="console-badge">{{ $link['badge'] > 99 ? '99+' : $link['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Account --}}
    <div class="relative border-t border-cream/10 p-3" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
        <div x-show="open" x-cloak
             x-transition:enter="transition-[opacity,transform] ease-out-soft duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition-opacity ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-x-3 bottom-full mb-2 overflow-hidden rounded-lg bg-cream py-1 text-sm text-lagoon-950 shadow-lift">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-3 py-2 transition-colors duration-150 hover:bg-sand-100">
                <x-console-icon name="profile" class="h-4 w-4 text-lagoon-700" />Profile
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-2.5 px-3 py-2 text-left transition-colors duration-150 hover:bg-sand-100">
                    <x-console-icon name="logout" class="h-4 w-4 text-lagoon-700" />Log out
                </button>
            </form>
        </div>

        <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
                class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors duration-200 hover:bg-cream/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-lagoon-300">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-cream text-xs font-semibold text-lagoon-950">
                {{ Str::of($user->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode('') }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-cream">{{ $user->name }}</span>
                <span class="block text-xs text-cream/55">{{ $console['roleLabel'] }}</span>
            </span>
            <svg class="h-4 w-4 shrink-0 text-cream/50" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 0 1-1.414 0L10 9.414l-3.293 3.293a1 1 0 0 1-1.414-1.414l4-4a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414Z" clip-rule="evenodd" /></svg>
        </button>
    </div>
</aside>
