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
        {{-- Non-breaking space keeps the middle dot on the first line when the title wraps on phones. --}}
        <x-page-header eyebrow="Owner console" :title="'Resort overview'.mb_chr(0xA0).'· '.now()->format('F Y')">
            What needs you now, this month’s figures so far, and what has happened lately.
            <x-slot name="actions">
                <a href="{{ route('admin.reports.index') }}" class="btn-primary">Full reports</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        {{-- What needs a person, first --}}
        <section aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="eyebrow">Needs attention</h2>

            <div class="card mt-3 overflow-hidden" x-reveal>
                @if (empty($attention))
                    <x-empty-state icon="compass">Nothing needs you right now.</x-empty-state>
                @else
                    <ul class="divide-y divide-sand-200">
                        @foreach ($attention as $item)
                            <li class="flex flex-col gap-2 px-5 py-4 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
                                <a href="{{ $item->url }}" class="group flex min-w-0 items-start gap-3">
                                    <span @class(['mt-[0.45rem] h-2 w-2 shrink-0 rounded-full',
                                                  'bg-coral-500' => in_array($item->tone, ['money', 'warning'], true),
                                                  'bg-sand-500' => $item->tone === 'attention',
                                                  'bg-ocean-500' => $item->tone === 'info'])></span>
                                    <span class="font-medium text-lagoon-950 decoration-lagoon-950/30 underline-offset-4 group-hover:underline">{{ $item->text }}</span>
                                </a>

                                @if ($item->bookings)
                                    <span class="flex flex-wrap gap-2 pl-5 lg:justify-end lg:pl-0">
                                        @foreach ($item->bookings as $booking)
                                            <a href="{{ route('staff.bookings.show', $booking) }}"
                                               class="inline-flex items-center gap-1.5 rounded-md border border-lagoon-950/10 bg-cream px-2 py-1 text-xs text-gray-700 transition-colors duration-200 hover:border-lagoon-950/25 hover:text-lagoon-950">
                                                <span class="ref-code text-sand-700">{{ $booking->reference_code }}</span>
                                                <span class="max-w-[9rem] truncate">{{ $booking->user->name }}</span>
                                            </a>
                                        @endforeach
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- This month, as one ledger row --}}
        <section aria-labelledby="month-heading">
            <h2 id="month-heading" class="eyebrow">This month so far</h2>
            <dl class="card mt-3 grid grid-cols-2 gap-px overflow-hidden bg-sand-200 sm:grid-cols-4" x-reveal>
                <div class="bg-white p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Occupancy</dt>
                    <dd class="mt-2 font-display text-2xl tabular-nums text-lagoon-900 sm:text-3xl">{{ $month['occupancy']['rate'] !== null ? $month['occupancy']['rate'].'%' : '—' }}</dd>
                    <dd class="mt-1 text-xs tabular-nums text-gray-600">{{ $month['occupancy']['occupied'] }} of {{ $month['occupancy']['available'] }} unit-nights</dd>
                </div>
                <div class="bg-white p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Collected</dt>
                    <dd class="mt-2 font-display text-2xl tabular-nums text-lagoon-900 sm:text-3xl">{{ Money::format($month['revenue']['net']) }}</dd>
                    <dd class="mt-1 text-xs text-gray-600">net of refunds</dd>
                </div>
                <div class="bg-white p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Still owed</dt>
                    <dd @class(['mt-2 font-display text-2xl tabular-nums sm:text-3xl', 'text-coral-700' => $today['owing_count'], 'text-lagoon-900' => ! $today['owing_count']])>{{ Money::format($today['owing_total']) }}</dd>
                    <dd class="mt-1 text-xs tabular-nums text-gray-600">{{ $today['owing_count'] }} confirmed or in-house {{ Str::plural('booking', $today['owing_count']) }}</dd>
                </div>
                <div class="bg-white p-5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">New requests</dt>
                    <dd class="mt-2 font-display text-2xl tabular-nums text-lagoon-900 sm:text-3xl">{{ $month['bookings']['received'] }}</dd>
                    <dd class="mt-1 text-xs text-gray-600">received this month</dd>
                </div>
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <x-report-bars title="Money collected, last 30 days" caption="Per day · net of refunds"
                           :bars="$trendBars" :ticks="[Money::isPositive($trendMax) ? Money::format($trendMax) : '', '', '₱0']" value-heading="Collected" />

            {{-- Recent activity --}}
            <section aria-labelledby="activity-heading" class="card p-5 sm:p-6" x-reveal>
                <h2 id="activity-heading" class="font-serif text-lg font-semibold text-lagoon-950">Recent activity</h2>

                @if (empty($activity))
                    <x-empty-state icon="anchor">Nothing has happened yet.</x-empty-state>
                @else
                    <ol class="mt-4 space-y-3">
                        @foreach ($activity as $entry)
                            <li class="flex gap-3 text-sm">
                                <time class="w-12 shrink-0 pt-px text-xs tabular-nums text-gray-500" datetime="{{ $entry->at->toIso8601String() }}">
                                    {{ $entry->at->isToday() ? $entry->at->format('H:i') : $entry->at->format('M j') }}
                                </time>
                                <span class="min-w-0 text-gray-800">
                                    @if ($entry->reference)
                                        <a href="{{ route('staff.bookings.show', $entry->reference) }}" class="decoration-lagoon-950/30 underline-offset-4 hover:underline">{{ $entry->text }}</a>
                                    @else
                                        {{ $entry->text }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
