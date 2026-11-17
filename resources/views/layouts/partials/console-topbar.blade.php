{{-- Sticky top bar. Desktop: booking search + bell. Phones: brand + a search toggle. --}}
<header class="sticky top-0 z-30 border-b border-lagoon-950/10 bg-cream/90 backdrop-blur-md print:hidden" x-data="{ searching: false }">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <x-brand :href="route($console['home'])" class="lg:hidden" />

        <form method="GET" action="{{ route('staff.bookings.index') }}" role="search" class="relative hidden w-full max-w-md lg:block">
            <input type="hidden" name="tab" value="all">
            <label for="console-search" class="sr-only">Find a booking</label>
            <x-console-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input id="console-search" type="search" name="q" autocomplete="off"
                   value="{{ request()->routeIs('staff.bookings.index') ? request('q') : '' }}"
                   placeholder="Find a booking — reference, guest name or email" class="field !py-2 pl-10">
        </form>

        <div class="ml-auto flex items-center gap-1">
            <button type="button" @click="searching = ! searching; $nextTick(() => searching && $refs.phoneSearch.focus())"
                    :aria-expanded="searching" aria-controls="console-search-row"
                    class="grid h-10 w-10 place-items-center rounded-md text-lagoon-800 transition-[background-color,color,transform] duration-200 hover:bg-sand-100 active:scale-95 lg:hidden">
                <x-console-icon name="search" class="h-5 w-5" />
                <span class="sr-only">Find a booking</span>
            </button>

            @include('layouts.partials.console-bell')
        </div>
    </div>

    <div id="console-search-row" x-show="searching" x-cloak
         x-transition:enter="transition-[opacity,transform] ease-out-soft duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="border-t border-lagoon-950/10 px-4 py-3 lg:hidden">
        <form method="GET" action="{{ route('staff.bookings.index') }}" role="search" class="relative">
            <input type="hidden" name="tab" value="all">
            <label for="console-search-phone" class="sr-only">Find a booking</label>
            <x-console-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input id="console-search-phone" x-ref="phoneSearch" type="search" name="q" autocomplete="off"
                   placeholder="Reference, guest name or email" class="field !py-2 pl-10">
        </form>
    </div>
</header>
