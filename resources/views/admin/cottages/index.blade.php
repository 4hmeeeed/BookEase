<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Owner console" title="Cottages & rates">
            Delisted units stay in your records but are hidden from guests and the chatbot.
            <x-slot name="actions">
                <a href="{{ route('admin.cottages.create') }}" class="btn-coral">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Add a unit
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
         x-data="{ target: { name: '', url: '' } }">

        {{-- Status tabs + search --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <nav class="inline-flex rounded-full border border-sand-200 bg-white/70 p-1 shadow-sm">
                @foreach (['all' => 'All', 'listed' => 'Listed', 'delisted' => 'Delisted'] as $key => $label)
                    @php $active = ($status ?? 'all') === $key || ($key === 'all' && ! in_array($status, ['listed', 'delisted'], true)); @endphp
                    <a href="{{ route('admin.cottages.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => request('q')])) }}"
                       @class(['rounded-full px-4 py-1.5 text-sm font-semibold transition-all duration-200 ease-out-soft active:scale-95',
                               'bg-lagoon-700 text-cream shadow-lagoon-glow' => $active,
                               'text-gray-600 hover:text-lagoon-900' => ! $active])>
                        {{ $label }} <span class="ms-1 opacity-70">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="relative w-full sm:w-72">
                @if (in_array($status, ['listed', 'delisted'], true))
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by name…" class="field !rounded-full !py-2 pl-10">
            </form>
        </div>

        @if ($cottages->isEmpty())
            <div class="card mt-8 animate-pop-in p-12 text-center">
                <p class="font-display text-2xl font-semibold text-lagoon-950">No units here yet</p>
                <p class="mt-2 text-gray-600">Add your first cottage, room, villa, or cabana so guests can start booking.</p>
                <a href="{{ route('admin.cottages.create') }}" class="btn-primary mt-6">Add a unit</a>
            </div>
        @else
            <ul class="mt-6 space-y-3" x-reveal.stagger>
                @foreach ($cottages as $cottage)
                    <li @class(['card group flex flex-col gap-4 p-3 transition-all duration-300 ease-out-soft hover:-translate-y-0.5 hover:shadow-lift sm:flex-row sm:items-center sm:p-4',
                                'opacity-70 hover:opacity-100' => ! $cottage->is_active])>
                        <x-cottage-image :cottage="$cottage" class="h-40 w-full shrink-0 rounded-2xl sm:h-20 sm:w-28" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate font-display text-lg font-semibold text-lagoon-950">{{ $cottage->name }}</h3>
                                @if ($cottage->is_active)
                                    <span class="badge badge-confirmed"><span class="badge-dot is-live"></span>Listed</span>
                                @else
                                    <span class="badge badge-checked-out"><span class="badge-dot"></span>Delisted</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $cottage->type->label() }} · up to {{ $cottage->capacity }} {{ Str::plural('guest', $cottage->capacity) }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-4 sm:justify-end">
                            <p class="text-right">
                                <span class="block font-display text-xl font-semibold text-coral-600">{{ $cottage->formatted_price }}</span>
                                <span class="block text-xs text-gray-500">per night</span>
                            </p>

                            <div class="flex items-center gap-1">
                                <form method="POST" action="{{ route('admin.cottages.toggle', $cottage) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded-full px-3 py-2 text-sm font-semibold text-lagoon-700 transition hover:bg-lagoon-50 active:scale-95"
                                            title="{{ $cottage->is_active ? 'Hide from guests' : 'Show to guests' }}">
                                        {{ $cottage->is_active ? 'Delist' : 'List' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.cottages.edit', $cottage) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-sand-100 active:scale-95">Edit</a>
                                <button type="button"
                                        @click="target = { name: @js($cottage->name), url: @js(route('admin.cottages.destroy', $cottage)) }; $dispatch('open-modal', 'confirm-delete')"
                                        class="rounded-full p-2 text-coral-600 transition hover:bg-coral-50 active:scale-90" aria-label="Delete {{ $cottage->name }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg>
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $cottages->links() }}</div>
        @endif

        <x-modal name="confirm-delete" maxWidth="md" focusable>
            <form method="POST" :action="target.url" class="p-6">
                @csrf @method('DELETE')
                <h2 class="font-display text-2xl font-semibold text-lagoon-950">Delete <span x-text="target.name"></span>?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    This permanently removes the unit and its photo. If guests have booked it before, it can't be deleted. Delist it instead so your records stay intact.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Keep it</x-secondary-button>
                    <x-danger-button>Delete permanently</x-danger-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
