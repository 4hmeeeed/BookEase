{{--
    The bell: a live count (checked every 30 s by consoleBell) and a dropdown of
    the latest alerts. Shown on every screen size; on phones the "Alerts" tab
    shows the same count too. Without JavaScript it is a plain link.
--}}
<div class="relative"
     x-data="consoleBell({ pollUrl: '{{ route('staff.notifications.poll') }}', panelUrl: '{{ route('staff.notifications.panel') }}', unread: {{ (int) $console['unread'] }} })"
     @click.outside="open = false" @keydown.escape.window="open = false">
    <a href="{{ route('staff.notifications.index') }}" data-unread="{{ $console['unread'] }}"
       @click.prevent="toggle()" :aria-expanded="open" aria-haspopup="true" aria-controls="console-bell-panel"
       class="relative grid h-10 w-10 place-items-center rounded-md text-lagoon-800 transition-[background-color,color,transform] duration-200 hover:bg-sand-100 hover:text-lagoon-950 active:scale-95">
        <span x-ref="bell" class="grid place-items-center" @animationend="$el.classList.remove('bell-ring')">
            <x-console-icon name="bell" class="h-5 w-5" />
        </span>
        <span class="sr-only">Notifications<span x-text="unread ? ', ' + unread + ' unread' : ''">{{ $console['unread'] ? ', '.$console['unread'].' unread' : '' }}</span></span>
        <span x-show="unread > 0" x-text="badge()" @if (! $console['unread']) x-cloak @endif aria-hidden="true"
              class="absolute -right-0.5 -top-0.5 grid h-[1.125rem] min-w-[1.125rem] place-items-center rounded-full bg-lagoon-950 px-1 text-[0.625rem] font-semibold tabular-nums text-cream ring-2 ring-cream">{{ $console['unread'] > 9 ? '9+' : $console['unread'] }}</span>
    </a>

    <div id="console-bell-panel" x-show="open" x-cloak
         x-transition:enter="transition-[opacity,transform] ease-out-soft duration-200" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition-[opacity,transform] ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-full z-50 mt-2 w-[calc(100vw-2rem)] max-w-96 origin-top-right">
        <div x-show="loading && ! panelHtml" class="rounded-2xl border border-lagoon-950/10 bg-cream px-4 py-8 text-center text-sm text-gray-500 shadow-lift">Loading…</div>
        <div x-html="panelHtml"></div>
    </div>

    <p class="sr-only" aria-live="polite" x-text="announcement"></p>
</div>
