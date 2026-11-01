<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Owner console" title="Chatbot audit log">
            Every question guests asked, with the answer the assistant gave. Use it to spot missing information or answers that need a policy update.
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4" x-reveal.stagger>
            @foreach ([
                ['Questions today', $stats['today'], 'text-lagoon-900'],
                ['Last 7 days', $stats['week'], 'text-lagoon-900'],
                ['Chats (7 days)', $stats['week_visitors'], 'text-ocean-800'],
                ['Fallback answers (7 days)', $stats['week_fallback'], $stats['week_fallback'] ? 'text-coral-700' : 'text-lagoon-900'],
            ] as [$label, $value, $tone])
                <div class="card tilt p-5" x-tilt="4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</dt>
                    <dd class="tilt-pop mt-2 font-display text-3xl font-semibold {{ $tone }}">{{ number_format($value) }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="flex gap-1" aria-label="Log filters">
                @foreach (['all' => 'All questions', 'fallback' => 'Fallback only'] as $key => $label)
                    <a href="{{ route('admin.chatbot.index', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $search])) }}"
                       @class(['rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200 ease-out-soft active:scale-95',
                               'bg-lagoon-700 text-cream shadow-lagoon-glow' => $filter === $key,
                               'text-gray-600 hover:bg-sand-100 hover:text-lagoon-900' => $filter !== $key])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="relative w-full lg:w-80">
                @if ($filter !== 'all')
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search questions or answers" class="field !rounded-full !py-2 pl-10">
            </form>
        </div>

        @if ($inquiries->isEmpty())
            <div class="card mt-6 animate-pop-in p-12 text-center">
                <p class="font-display text-2xl font-semibold text-lagoon-950">No questions yet</p>
                <p class="mt-1 text-gray-600">{{ $search ? 'Nothing matches your search.' : 'Questions from the website chat will appear here.' }}</p>
            </div>
        @else
            <ul class="mt-6 space-y-3" x-reveal.stagger>
                @foreach ($inquiries as $inquiry)
                    <li>
                        <a href="{{ route('admin.chatbot.show', $inquiry) }}"
                           class="card group grid gap-3 p-4 transition-all duration-300 ease-out-soft hover:-translate-y-0.5 hover:shadow-lift sm:grid-cols-[9rem_1fr_auto] sm:items-start">
                            <span class="text-sm text-gray-500">
                                <span class="block font-semibold text-gray-700">{{ $inquiry->created_at->format('M j, g:i A') }}</span>
                                {{ $inquiry->user?->name ?? 'Visitor' }}
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-gray-900">{{ $inquiry->question }}</span>
                                <span class="mt-0.5 line-clamp-2 text-sm text-gray-600">{{ $inquiry->response }}</span>
                            </span>
                            @if ($inquiry->was_fallback)
                                <span class="badge badge-rejected justify-self-start sm:justify-self-end">Fallback</span>
                            @else
                                <span class="badge badge-confirmed justify-self-start sm:justify-self-end">Answered</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $inquiries->links() }}</div>
        @endif
    </div>
</x-app-layout>
