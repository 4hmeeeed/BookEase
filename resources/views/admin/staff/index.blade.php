<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Owner console" title="Staff accounts">
            Front-desk staff can review bookings, record cash payments and refunds, and check guests in and out.
            Deactivate an account when someone leaves. Their name stays on the records they made.
            <x-slot name="actions">
                <a href="{{ route('admin.staff.create') }}" class="btn-coral">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Add staff
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <nav class="inline-flex self-start rounded-lg border border-sand-200 bg-white/70 p-1 shadow-sm" aria-label="Staff filters">
                @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Deactivated'] as $key => $label)
                    <a href="{{ route('admin.staff.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search])) }}"
                       @class(['rounded-md px-4 py-1.5 text-sm font-semibold transition-all duration-200 ease-out-soft active:scale-95',
                               'bg-lagoon-950 text-cream' => $status === $key,
                               'text-gray-600 hover:text-lagoon-900' => $status !== $key])>
                        {{ $label }} <span class="ms-1 opacity-70">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="relative w-full sm:w-72">
                @if ($status !== 'all')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or email" class="field !py-2 pl-10">
            </form>
        </div>

        @if ($staff->isEmpty())
            <div class="card mt-8 animate-pop-in p-12 text-center">
                <p class="font-serif text-2xl font-semibold text-lagoon-950">{{ $search ? 'No matches' : 'No staff accounts yet' }}</p>
                <p class="mt-2 text-gray-600">{{ $search ? 'Try another name or email.' : 'Add your front-desk team so they can manage bookings.' }}</p>
                @unless ($search)
                    <a href="{{ route('admin.staff.create') }}" class="btn-primary mt-6">Add staff</a>
                @endunless
            </div>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
                @foreach ($staff as $member)
                    <li @class(['card flex flex-col p-5', 'opacity-70 hover:opacity-100' => ! $member->is_active])>
                        <div class="tilt-pop flex items-start gap-3">
                            <span @class(['grid h-11 w-11 shrink-0 place-items-center rounded-full font-display text-lg font-semibold',
                                          'bg-lagoon-100 text-lagoon-800' => $member->is_active,
                                          'bg-gray-200 text-gray-500' => ! $member->is_active])>
                                {{ Str::upper(Str::substr($member->name, 0, 1)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-gray-900">{{ $member->name }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $member->email }}</p>
                                @if ($member->phone)
                                    <p class="text-sm text-gray-500">{{ $member->phone }}</p>
                                @endif
                            </div>
                            @if ($member->is_active)
                                <span class="badge badge-confirmed"><span class="badge-dot is-live"></span>Active</span>
                            @else
                                <span class="badge badge-cancelled"><span class="badge-dot"></span>Deactivated</span>
                            @endif
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-xl bg-sand-50 px-3 py-2">
                                <dt class="text-xs text-gray-500">Booking actions</dt>
                                <dd class="font-semibold text-gray-900">{{ number_format($member->booking_decisions_count) }}</dd>
                            </div>
                            <div class="rounded-xl bg-sand-50 px-3 py-2">
                                <dt class="text-xs text-gray-500">Payments recorded</dt>
                                <dd class="font-semibold text-gray-900">{{ number_format($member->recorded_payments_count) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-gray-500">Added {{ $member->created_at->format('M j, Y') }}</p>

                        <div class="mt-auto flex gap-2 pt-4">
                            <a href="{{ route('admin.staff.edit', $member) }}" class="btn-ghost flex-1 justify-center">Edit</a>
                            <form method="POST" action="{{ route('admin.staff.toggle', $member) }}" class="flex-1"
                                  @if ($member->is_active) onsubmit="return confirm('Deactivate {{ e(addslashes($member->name)) }}? They will be signed out immediately.')" @endif>
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="{{ $member->is_active ? 'btn-danger' : 'btn-primary' }} w-full justify-center">
                                    {{ $member->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $staff->links() }}</div>
        @endif
    </div>
</x-app-layout>
