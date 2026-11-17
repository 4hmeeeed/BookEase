{{-- Phones and tablets (< lg): bottom tabs, plus a "More" sheet for everything else. --}}
@php $user = auth()->user(); @endphp

<div x-data="{ more: false }" @keydown.escape.window="more = false" class="print:hidden lg:hidden">
    <nav data-console-tabbar aria-label="Console"
         class="fixed inset-x-0 bottom-0 z-40 border-t border-lagoon-950/10 bg-cream/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md">
        <ul class="grid h-14 grid-cols-4">
            @foreach ($console['tabs'] as $tab)
                @php $active = request()->routeIs($tab['pattern']); @endphp
                <li>
                    <a href="{{ route($tab['route']) }}" @class(['console-tab', 'is-active' => $active]) @if ($active) aria-current="page" @endif>
                        <span class="relative">
                            <x-console-icon :name="$tab['icon']" class="h-5 w-5" />
                            @if ($tab['badge'])
                                <span class="absolute -right-2.5 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-lagoon-950 px-1 text-[0.6rem] font-semibold tabular-nums text-cream ring-2 ring-cream" aria-hidden="true">{{ $tab['badge'] > 9 ? '9+' : $tab['badge'] }}</span>
                            @endif
                        </span>
                        <span>{{ $tab['label'] }}</span>
                    </a>
                </li>
            @endforeach
            <li>
                <button type="button" @click="more = true" :aria-expanded="more" aria-controls="console-more" class="console-tab w-full">
                    <x-console-icon name="more" class="h-5 w-5" />
                    <span>More</span>
                </button>
            </li>
        </ul>
    </nav>

    {{-- Kept outside the blurred bar: a backdrop-filter would trap these fixed layers inside it. --}}
    <div x-show="more" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-lagoon-950/40" @click="more = false"
             x-show="more"
             x-transition:enter="transition-opacity ease-out-soft duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        <div id="console-more" role="dialog" aria-modal="true" aria-label="More"
             x-show="more" x-effect="more && $nextTick(() => $el.querySelector('a, button')?.focus())"
             x-transition:enter="transition-[transform,opacity] ease-out-soft duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition-[transform,opacity] ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0"
             class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-cream pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-plate">
            <div class="mx-auto mt-3 h-1 w-10 rounded-full bg-gray-300" aria-hidden="true"></div>

            <div class="flex items-center gap-3 px-5 pb-3 pt-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-lagoon-950 text-xs font-semibold text-cream">
                    {{ Str::of($user->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode('') }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-lagoon-950">{{ $user->name }}</span>
                    <span class="block text-sm text-gray-600">{{ $console['roleLabel'] }}</span>
                </span>
            </div>

            <ul class="border-t border-lagoon-950/10 px-2 pt-2">
                @foreach ($console['more'] as $link)
                    <li>
                        <a href="{{ route($link['route']) }}" class="flex items-center gap-3 rounded-md px-3 py-3 text-base text-lagoon-950 transition-colors duration-150 hover:bg-sand-100">
                            <x-console-icon :name="$link['icon']" class="h-5 w-5 text-lagoon-700" />
                            <span class="flex-1">{{ $link['label'] }}</span>
                            @if ($link['badge'])
                                <span class="console-badge">{{ $link['badge'] > 99 ? '99+' : $link['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
                <li>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-md px-3 py-3 text-base text-lagoon-950 transition-colors duration-150 hover:bg-sand-100">
                        <x-console-icon name="profile" class="h-5 w-5 text-lagoon-700" />Profile
                    </a>
                </li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-base text-lagoon-950 transition-colors duration-150 hover:bg-sand-100">
                            <x-console-icon name="logout" class="h-5 w-5 text-lagoon-700" />Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>
