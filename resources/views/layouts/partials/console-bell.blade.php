{{-- The bell (desktop). Phones reach the same list from the "Alerts" tab. --}}
<a href="{{ route('staff.notifications.index') }}" data-unread="{{ $console['unread'] }}"
   class="relative hidden h-10 w-10 place-items-center rounded-md text-lagoon-800 transition-[background-color,color,transform] duration-200 hover:bg-sand-100 hover:text-lagoon-950 active:scale-95 lg:grid">
    <x-console-icon name="bell" class="h-5 w-5" />
    <span class="sr-only">Notifications{{ $console['unread'] ? ', '.$console['unread'].' unread' : '' }}</span>
    @if ($console['unread'] > 0)
        <span class="absolute -right-0.5 -top-0.5 grid h-[1.125rem] min-w-[1.125rem] place-items-center rounded-full bg-lagoon-950 px-1 text-[0.625rem] font-semibold tabular-nums text-cream ring-2 ring-cream" aria-hidden="true">
            {{ $console['unread'] > 9 ? '9+' : $console['unread'] }}
        </span>
    @endif
</a>
