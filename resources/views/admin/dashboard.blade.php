@use('App\Support\Money')

@php
    $trendMax = collect($trend)->reduce(fn ($m, $d) => Money::max($m, $d['collected']), '0.00');
    $trendBars = array_map(fn ($d) => [
        'label' => $d['date']->format('j'),
        'title' => $d['date']->format('D, M j'),
        'value' => Money::format($d['collected']),
        'height' => Money::isPositive($trendMax) && Money::isPositive($d['collected']) ? bcdiv(bcmul($d['collected'], '100', 2), $trendMax, 1) : '0',
    ], $trend);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Owner console" :title="'Resort overview · '.now()->format('F Y')">
            Today’s front desk, this month’s figures, and quick links to everything you manage.
            <x-slot name="actions">
                <a href="{{ route('admin.reports.index') }}" class="btn-primary">Full reports</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        {{-- Today --}}
        <section aria-labelledby="today-heading">
            <h2 id="today-heading" class="eyebrow">Today at the desk</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4" x-reveal.stagger>
                @foreach ([
                    ['Pending requests', $today['pending'], route('staff.bookings.index', ['tab' => 'pending']), $today['pending'] ? 'text-coral-700' : 'text-lagoon-900'],
                    ['Arriving today', $today['arrivals'], route('staff.bookings.index', ['tab' => 'arrivals']), 'text-lagoon-900'],
                    ['In house', $today['in_house'], route('staff.bookings.index', ['tab' => 'in_house']), 'text-lagoon-900'],
                    ['Due to check out', $today['departures'], route('staff.bookings.index', ['tab' => 'departures']), 'text-lagoon-900'],
                ] as [$label, $value, $href, $tone])
                    <a href="{{ $href }}" class="card tilt group block p-5" x-tilt="5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</p>
                        <p class="tilt-pop mt-2 font-display text-4xl font-semibold {{ $tone }}">{{ $value }}</p>
                        <p class="mt-2 text-xs font-semibold text-lagoon-700 opacity-0 transition-opacity duration-200 group-hover:opacity-100">Open list →</p>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- This month --}}
        <section aria-labelledby="month-heading" class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
            <div>
                <h2 id="month-heading" class="eyebrow">This month so far</h2>
                <dl class="mt-3 grid grid-cols-2 gap-4" x-reveal.stagger>
                    <div class="card p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Occupancy</dt>
                        <dd class="mt-2 font-display text-3xl font-semibold text-lagoon-900">{{ $month['occupancy']['rate'] !== null ? $month['occupancy']['rate'].'%' : '—' }}</dd>
                        <dd class="mt-1 text-xs text-gray-600">{{ $month['occupancy']['occupied'] }} of {{ $month['occupancy']['available'] }} unit-nights</dd>
                    </div>
                    <div class="card p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Collected</dt>
                        <dd class="mt-2 font-display text-3xl font-semibold text-lagoon-900">{{ Money::format($month['revenue']['net']) }}</dd>
                        <dd class="mt-1 text-xs text-gray-600">net of refunds</dd>
                    </div>
                    <div class="card p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Still owed</dt>
                        <dd @class(['mt-2 font-display text-3xl font-semibold', 'text-coral-700' => $today['owing_count'], 'text-lagoon-900' => ! $today['owing_count']])>{{ Money::format($today['owing_total']) }}</dd>
                        <dd class="mt-1 text-xs text-gray-600">{{ $today['owing_count'] }} confirmed or in-house {{ Str::plural('booking', $today['owing_count']) }}</dd>
                    </div>
                    <div class="card p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">New requests</dt>
                        <dd class="mt-2 font-display text-3xl font-semibold text-lagoon-900">{{ $month['bookings']['received'] }}</dd>
                        <dd class="mt-1 text-xs text-gray-600">received this month</dd>
                    </div>
                </dl>
            </div>

            <x-report-bars class="self-end" title="Money collected, last 30 days" caption="Per day · net of refunds"
                           :bars="$trendBars" :ticks="[Money::format($trendMax), '', '₱0']" value-heading="Collected" />
        </section>

        {{-- Modules --}}
        <section aria-labelledby="manage-heading">
            <h2 id="manage-heading" class="eyebrow">Manage</h2>
            <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" x-reveal.stagger>
                <x-module-card title="Cottages & rates" tone="lagoon" :href="route('admin.cottages.index')">
                    <x-slot name="icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11 12 4l9 7M5 10v10h14V10M10 20v-5h4v5" /></svg>
                    </x-slot>
                    {{ $today['units'] }} {{ Str::plural('unit', $today['units']) }} listed. Add and edit units, photos, and nightly rates.
                </x-module-card>

                <x-module-card title="Staff accounts" tone="sand" :href="route('admin.staff.index')">
                    <x-slot name="icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5" /><path stroke-linecap="round" d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5" /></svg>
                    </x-slot>
                    {{ $staffCount }} active {{ Str::plural('account', $staffCount) }}. Add front-desk staff or deactivate someone who left.
                </x-module-card>

                <x-module-card title="Reports" tone="ocean" :href="route('admin.reports.index')">
                    <x-slot name="icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></svg>
                    </x-slot>
                    Occupancy, collections, balances, and unit performance for any period, with CSV export.
                </x-module-card>

                <x-module-card title="Chatbot audit log" tone="coral" :href="route('admin.chatbot.index')">
                    <x-slot name="icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H9l-5 4V5Z" /><path stroke-linecap="round" d="M8 9h8M8 12h5" /></svg>
                    </x-slot>
                    {{ $chat['week'] }} {{ Str::plural('question', $chat['week']) }} in the last 7 days{{ $chat['fallback'] ? ", {$chat['fallback']} without an answer" : '' }}.
                </x-module-card>

                <x-module-card title="Front desk" tone="lagoon" :href="route('staff.dashboard')">
                    <x-slot name="icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </x-slot>
                    Jump into the staff view to approve bookings or cover the desk.
                </x-module-card>
            </div>
        </section>
    </div>
</x-app-layout>
