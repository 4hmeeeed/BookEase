<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Chatbot audit log" title="Inquiry #{{ $inquiry->id }}">
            {{ $inquiry->created_at->format('l, M j, Y · g:i A') }} ·
            {{ $inquiry->user ? $inquiry->user->name.' ('.$inquiry->user->email.')' : 'Visitor (not signed in)' }}
        </x-page-header>
    </x-slot>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_26rem] lg:px-8">
        <div class="space-y-6">
            <a href="{{ route('admin.chatbot.index') }}" class="link-underline text-sm font-semibold text-lagoon-800">← Back to the log</a>

            <section class="card animate-fade-up p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="eyebrow">This exchange</h2>
                    @if ($inquiry->was_fallback)
                        <span class="badge badge-rejected">Fallback text shown</span>
                    @else
                        <span class="badge badge-confirmed">Answered by the model</span>
                    @endif
                </div>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Question</p>
                <p class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $inquiry->question }}</p>

                <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-gray-500">Answer</p>
                <p class="mt-1 whitespace-pre-line break-words rounded-2xl bg-sand-50 p-4 text-gray-800">{{ $inquiry->response }}</p>
            </section>

            @if ($conversation->count() > 1)
                <section class="card animate-fade-up p-6 [animation-delay:80ms]">
                    <h2 class="eyebrow">Same chat (within 2 hours)</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ($conversation as $turn)
                            <li @class(['rounded-2xl p-4', 'bg-lagoon-50 ring-2 ring-lagoon-300' => $turn->id === $inquiry->id, 'bg-sand-50' => $turn->id !== $inquiry->id])>
                                <p class="text-xs text-gray-500">{{ $turn->created_at->format('g:i:s A') }} @if ($turn->was_fallback) · <span class="font-semibold text-coral-700">fallback</span> @endif</p>
                                <p class="mt-1 font-semibold text-gray-900">{{ $turn->question }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $turn->response }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>

        <aside class="card h-fit animate-fade-up p-6 [animation-delay:120ms] lg:sticky lg:top-24" x-data="{ open: false }">
            <h2 class="eyebrow">Resort data given to the model</h2>
            <p class="mt-2 text-sm text-gray-600">The exact records the assistant could see when it answered. If an answer is wrong or incomplete, check whether the information was here.</p>
            <button type="button" @click="open = !open" class="btn-ghost mt-4 w-full justify-center" :aria-expanded="open.toString()">
                <span x-text="open ? 'Hide context' : 'Show context'">Show context</span>
            </button>
            <pre x-show="open" x-cloak x-transition.opacity.duration.200ms
                 class="mt-4 max-h-[60vh] overflow-auto whitespace-pre-wrap break-words rounded-2xl bg-lagoon-950 p-4 font-mono text-xs leading-relaxed text-lagoon-50">{{ $inquiry->context_snapshot ?? '(no context recorded)' }}</pre>
        </aside>
    </div>
</x-app-layout>
