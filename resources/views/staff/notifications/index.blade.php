<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Notifications">
            New requests, payments, cancellations and anything else the desk should know about.
            @if ($counts['unread'] > 0)
                <x-slot name="actions">
                    <form method="POST" action="{{ route('staff.notifications.read-all') }}">
                        @csrf
                        <button class="btn-ghost">Mark all read</button>
                    </form>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="flex gap-1" aria-label="Notification filters">
            @foreach (['unread' => 'Unread', 'all' => 'All'] as $key => $label)
                <a href="{{ route('staff.notifications.index', $key === 'all' ? ['filter' => 'all'] : []) }}"
                   @if ($filter === $key) aria-current="page" @endif
                   @class(['inline-flex shrink-0 items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold transition-[background-color,color,box-shadow,transform] duration-200 ease-out-soft active:scale-95',
                           'bg-lagoon-950 text-cream' => $filter === $key,
                           'text-gray-600 hover:bg-sand-100 hover:text-lagoon-900' => $filter !== $key])>
                    {{ $label }}
                    <span @class(['rounded-md px-1.5 text-xs tabular-nums', 'bg-lagoon-600' => $filter === $key, 'bg-sand-200 text-gray-700' => $filter !== $key])>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="card mt-6 overflow-hidden" x-reveal>
            @if ($alerts->isEmpty())
                <x-empty-state icon="compass">{{ $filter === 'all' ? 'No notifications yet.' : 'You’re all caught up.' }}</x-empty-state>
            @else
                <ul class="divide-y divide-sand-200">
                    @include('staff.notifications._items', ['alerts' => $alerts])
                </ul>
            @endif
        </div>

        <div class="mt-6">{{ $alerts->links() }}</div>
    </div>
</x-app-layout>
