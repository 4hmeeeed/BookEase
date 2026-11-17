{{-- The bell's dropdown, loaded on demand (a fragment, no layout). --}}
<div class="overflow-hidden rounded-2xl border border-lagoon-950/10 bg-cream shadow-lift">
    <div class="flex items-center justify-between gap-3 border-b border-lagoon-950/10 px-4 py-3">
        <p class="font-serif text-base text-lagoon-950">Notifications</p>
        @if ($unread > 0)
            <form method="POST" action="{{ route('staff.notifications.read-all') }}">
                @csrf
                <button class="text-xs font-semibold text-lagoon-700 transition-colors duration-200 hover:text-lagoon-950">Mark all read</button>
            </form>
        @endif
    </div>

    @if ($alerts->isEmpty())
        <x-empty-state icon="compass">You’re all caught up.</x-empty-state>
    @else
        <ul class="max-h-[min(28rem,70vh)] divide-y divide-sand-200 overflow-y-auto">
            @include('staff.notifications._items', ['alerts' => $alerts])
        </ul>
    @endif

    <a href="{{ route('staff.notifications.index') }}"
       class="block border-t border-lagoon-950/10 px-4 py-2.5 text-center text-sm font-semibold text-lagoon-800 transition-colors duration-200 hover:bg-sand-50">
        See all
    </a>
</div>
