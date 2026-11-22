@use('App\Enums\BookingStatus')
@use('App\Enums\PaymentMethod')
@use('App\Support\Money')

@php
    $q = $period->query();
    $bucketWord = $series['unit'] === 'month' ? 'month' : 'day';

    $occupancyBars = array_map(fn ($b) => [
        'label' => $b['label'],
        'title' => $b['title'],
        'value' => "{$b['rate']}% · {$b['occupied']} of {$b['available']} unit-nights",
        'height' => $b['rate'],
        'muted' => $b['weekend'] ?? false,
    ], $series['buckets']);

    $revenueBars = array_map(fn ($b) => [
        'label' => $b['label'],
        'title' => $b['title'],
        'value' => Money::format($b['collected']),
        'height' => $b['collected_height'],
    ], $series['buckets']);

    $maxMethod = collect($revenue['by_method'])->reduce(fn ($m, $v) => Money::max($m, $v), '0.00');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header eyebrow="Reports" :title="$period->label()">
            Occupancy, money collected, balances still owed, and how each unit performed.
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2 print:hidden">
                    <a href="{{ route('admin.reports.bookings', $q) }}" class="btn-ghost">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14" /></svg>
                        Bookings CSV
                    </a>
                    <a href="{{ route('admin.reports.payments', $q) }}" class="btn-ghost">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14" /></svg>
                        Payments CSV
                    </a>
                    <button type="button" onclick="window.print()" class="btn-primary">Print</button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        {{-- Period picker --}}
        <div class="card flex flex-col gap-4 p-4 print:hidden lg:flex-row lg:items-center lg:justify-between">
            <nav class="flex flex-wrap gap-1" aria-label="Report period">
                @foreach (\App\Support\ReportPeriod::PRESETS as $key => $label)
                    <a href="{{ route('admin.reports.index', ['period' => $key]) }}"
                       @class(['shrink-0 rounded-md px-4 py-2 text-sm font-semibold transition-[background-color,color,box-shadow,transform] duration-200 ease-out-soft active:scale-95',
                               'bg-lagoon-950 text-cream' => $period->preset === $key,
                               'text-gray-600 hover:bg-sand-100 hover:text-lagoon-900' => $period->preset !== $key])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="flex flex-wrap items-end gap-2">
                <label class="text-xs font-semibold text-gray-600">
                    From
                    <input type="date" name="from" value="{{ $q['from'] }}" class="field mt-1 !py-2 text-sm" required>
                </label>
                <label class="text-xs font-semibold text-gray-600">
                    To
                    <input type="date" name="to" value="{{ $q['to'] }}" class="field mt-1 !py-2 text-sm" required>
                </label>
                <button type="submit" @class(['btn-primary !py-2.5', 'ring-2 ring-coral-300 ring-offset-2' => $period->preset === 'custom'])>Apply</button>
            </form>
        </div>

        {{-- Headline figures --}}
        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4" x-reveal.stagger>
            <div class="card p-5">
                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Occupancy</dt>
                <dd class="mt-2 font-display text-2xl font-semibold sm:text-3xl text-lagoon-900">{{ $occupancy['rate'] !== null ? $occupancy['rate'].'%' : '—' }}</dd>
                <dd class="mt-1 text-xs text-gray-600">{{ number_format($occupancy['occupied']) }} of {{ number_format($occupancy['available']) }} unit-nights sold</dd>
            </div>
            <div class="card p-5">
                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Collected (net)</dt>
                <dd class="mt-2 font-display text-2xl font-semibold sm:text-3xl text-lagoon-900">{{ Money::format($revenue['net']) }}</dd>
                <dd class="mt-1 text-xs text-gray-600">
                    {{ $revenue['count'] }} {{ Str::plural('payment', $revenue['count']) }}
                    @if (Money::isPositive($revenue['refunded'])) · {{ Money::format($revenue['refunded']) }} refunded @endif
                </dd>
            </div>
            <div class="card p-5">
                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Outstanding</dt>
                <dd @class(['mt-2 font-display text-2xl font-semibold sm:text-3xl', 'text-coral-700' => $outstanding['count'], 'text-lagoon-900' => ! $outstanding['count']])>{{ Money::format($outstanding['total']) }}</dd>
                <dd class="mt-1 text-xs text-gray-600">{{ $outstanding['count'] }} {{ Str::plural('booking', $outstanding['count']) }} with a balance</dd>
            </div>
            <div class="card p-5">
                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500">Stays starting</dt>
                <dd class="mt-2 font-display text-2xl font-semibold sm:text-3xl text-lagoon-900">{{ number_format($bookings['arriving']) }}</dd>
                <dd class="mt-1 text-xs text-gray-600">{{ number_format($bookings['received']) }} new {{ Str::plural('request', $bookings['received']) }} received</dd>
            </div>
        </dl>

        {{-- Trends --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <x-report-bars title="Occupancy" :caption="'By '.$bucketWord.' · % of unit-nights sold'.($bucketWord === 'day' ? ' · weekends lighter' : '')"
                           :bars="$occupancyBars" :ticks="['100%', '50%', '0%']" value-heading="Occupancy" />
            <x-report-bars title="Money collected" :caption="'By '.$bucketWord.' · net of refunds'"
                           :bars="$revenueBars" :ticks="[Money::isPositive($series['max_collected']) ? Money::format($series['max_collected']) : '', '', '₱0']" value-heading="Collected" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
            {{-- By payment method --}}
            <section class="card p-5 sm:p-6">
                <h2 class="font-serif text-lg font-semibold text-lagoon-950">Collected by method</h2>
                <ul class="mt-5 space-y-4">
                    @foreach (PaymentMethod::cases() as $method)
                        @php $amount = $revenue['by_method'][$method->value]; @endphp
                        @continue($method === PaymentMethod::Card && ! Money::isPositive($amount))
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-semibold text-gray-800">{{ $method->label() }}</span>
                                <span class="tabular-nums text-gray-700">{{ Money::format($amount) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-sm bg-sand-100">
                                <div class="h-full bg-lagoon-600 transition-[width] duration-700 ease-out-soft"
                                     style="width: {{ Money::isPositive($maxMethod) && Money::isPositive($amount) ? bcdiv(bcmul($amount, '100', 2), $maxMethod, 1) : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-5 border-t border-sand-200 pt-3 text-xs text-gray-500">
                    Received {{ Money::format($revenue['received']) }} − refunds {{ Money::format($revenue['refunded']) }} = {{ Money::format($revenue['net']) }}.
                    GCash and Maya amounts come only from verified gateway callbacks.
                </p>
            </section>

            {{-- Bookings by status --}}
            <section class="card p-5 sm:p-6">
                <h2 class="font-serif text-lg font-semibold text-lagoon-950">Stays starting in this period, by status</h2>
                <ul class="mt-5 divide-y divide-sand-100">
                    @foreach (BookingStatus::cases() as $status)
                        <li class="flex items-center justify-between py-2.5">
                            <x-booking-status :status="$status" />
                            <span class="font-semibold tabular-nums text-gray-800">{{ number_format($bookings['by_status'][$status->value]) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- Cottage performance --}}
        <section class="card overflow-hidden">
            <div class="p-5 sm:p-6">
                <h2 class="font-serif text-lg font-semibold text-lagoon-950">Unit performance</h2>
                <p class="mt-1 text-sm text-gray-600">Sold nights and money collected per unit in this period.</p>
            </div>
            {{-- Phones: one card per unit so every figure stays visible --}}
            <ul class="divide-y divide-sand-100 border-t border-sand-200 sm:hidden">
                @foreach ($cottages as $row)
                    <li class="px-5 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="font-semibold text-gray-900">{{ $row['cottage']->name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-gray-900">{{ Money::format($row['collected']) }}</span>
                        </div>
                        <p class="text-xs text-gray-500">{{ $row['cottage']->type->label() }} · {{ $row['cottage']->formatted_price }}/night @unless ($row['cottage']->is_active) · delisted @endunless</p>
                        <div class="mt-2 flex items-center gap-3 text-xs text-gray-600">
                            <div class="h-2 flex-1 overflow-hidden rounded-sm bg-sand-100">
                                <div class="h-full bg-lagoon-600" style="width: {{ $row['rate'] ?? 0 }}%"></div>
                            </div>
                            <span class="shrink-0 tabular-nums">{{ $row['rate'] ?? '0.0' }}% · {{ $row['nights'] }}/{{ $period->days() }} nights · {{ $row['stays'] }} {{ Str::plural('stay', $row['stays']) }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="border-y border-sand-200 bg-sand-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Unit</th>
                            <th class="px-5 py-3 text-right font-semibold">Stays starting</th>
                            <th class="px-5 py-3 text-right font-semibold">Nights sold</th>
                            <th class="px-5 py-3 font-semibold">Occupancy</th>
                            <th class="px-5 py-3 text-right font-semibold">Collected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @forelse ($cottages as $row)
                            <tr class="transition-colors hover:bg-sand-50/60">
                                <td class="px-5 py-3">
                                    <span class="font-semibold text-gray-900">{{ $row['cottage']->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $row['cottage']->type->label() }} · {{ $row['cottage']->formatted_price }}/night @unless ($row['cottage']->is_active) · delisted @endunless</span>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $row['stays'] }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $row['nights'] }} / {{ $period->days() }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-24 overflow-hidden rounded-sm bg-sand-100">
                                            <div class="h-full bg-lagoon-600" style="width: {{ $row['rate'] ?? 0 }}%"></div>
                                        </div>
                                        <span class="tabular-nums text-gray-700">{{ $row['rate'] ?? '0.0' }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ Money::format($row['collected']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No units yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Outstanding balances --}}
        <section class="card overflow-hidden">
            <div class="p-5 sm:p-6">
                <h2 class="font-serif text-lg font-semibold text-lagoon-950">Outstanding balances</h2>
                <p class="mt-1 text-sm text-gray-600">Active bookings checking in during this period that aren’t fully paid.</p>
            </div>
            @if ($outstanding['bookings']->isEmpty())
                <p class="border-t border-sand-200 px-6 py-8 text-center text-gray-500">Nothing owed for stays in this period.</p>
            @else
                <ul class="divide-y divide-sand-100 border-t border-sand-200 sm:hidden">
                    @foreach ($outstanding['bookings'] as $booking)
                        <li>
                            <a href="{{ route('staff.bookings.show', $booking) }}" class="block px-5 py-4 transition-colors active:bg-sand-50">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="ref-code text-sm text-sand-700">{{ $booking->reference_code }}</span>
                                    <span class="font-semibold tabular-nums text-coral-700">{{ Money::format($booking->balance()) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-800">{{ $booking->user?->name }} · {{ $booking->cottage?->name }}</p>
                                <div class="mt-1.5 flex items-center justify-between gap-3 text-xs text-gray-500">
                                    <span>Check-in {{ $booking->check_in->format('M j, Y') }} · paid {{ Money::format($booking->amount_paid) }} of {{ Money::format($booking->total_price) }}</span>
                                    <x-booking-status :status="$booking->status" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full min-w-[44rem] text-left text-sm">
                        <thead class="border-y border-sand-200 bg-sand-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Booking</th>
                                <th class="px-5 py-3 font-semibold">Guest · unit</th>
                                <th class="px-5 py-3 font-semibold">Check-in</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Paid / total</th>
                                <th class="px-5 py-3 text-right font-semibold">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($outstanding['bookings'] as $booking)
                                <tr class="transition-colors hover:bg-sand-50/60">
                                    <td class="px-5 py-3"><a href="{{ route('staff.bookings.show', $booking) }}" class="link-underline ref-code text-sand-700">{{ $booking->reference_code }}</a></td>
                                    <td class="px-5 py-3">{{ $booking->user?->name }}<span class="block text-xs text-gray-500">{{ $booking->cottage?->name }}</span></td>
                                    <td class="px-5 py-3">{{ $booking->check_in->format('M j, Y') }}</td>
                                    <td class="px-5 py-3"><x-booking-status :status="$booking->status" /></td>
                                    <td class="px-5 py-3 text-right tabular-nums text-gray-600">{{ Money::format($booking->amount_paid) }} / {{ Money::format($booking->total_price) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-coral-700">{{ Money::format($booking->balance()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <details class="card p-5 text-sm text-gray-700 sm:p-6">
            <summary class="cursor-pointer font-semibold text-lagoon-900">How these numbers are counted</summary>
            <ul class="mt-3 list-disc space-y-1.5 pl-5">
                <li><strong>Unit-night:</strong> one unit for one night. A stay from Dec 10 to Dec 13 is three nights (10th, 11th, 12th).</li>
                <li><strong>Occupancy</strong> counts nights of confirmed, checked-in, and checked-out bookings. Pending requests aren’t counted until staff approve them.</li>
                <li><strong>Available unit-nights</strong> = listed units × {{ $period->days() }} {{ Str::plural('day', $period->days()) }} (plus any delisted unit that had guests in this period).</li>
                <li><strong>Collected</strong> is money actually received in this period (by payment date), minus refunds made in this period. Unfinished or failed online payments are not included.</li>
                <li><strong>Outstanding</strong> is total price minus amount paid for pending, confirmed, checked-in, and checked-out bookings whose check-in is in this period.</li>
            </ul>
        </details>
    </div>
</x-app-layout>
