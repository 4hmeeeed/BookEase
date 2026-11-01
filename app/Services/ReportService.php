<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Support\Money;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Figures for the owner's dashboard and reports (thesis module 10).
 *
 * Definitions, so the numbers can be checked by hand:
 *  - Occupied night: a night inside the period held by a confirmed,
 *    checked-in, or checked-out booking (pending requests are not counted).
 *  - Available nights: units × days in the period. Units are the cottages
 *    listed now plus any delisted unit that was occupied in the period.
 *  - Occupancy rate: occupied ÷ available, as a percentage (1 decimal).
 *  - Collected: settled payments whose paid_at falls in the period, minus
 *    refunds settled in the period. Pending/failed/expired attempts are ignored.
 *  - Outstanding: bookings checking in during the period that are still
 *    active (pending, confirmed, checked in, or checked out) and whose total
 *    exceeds the amount paid.
 *
 * Money is summed with bcmath in PHP; nights are integers.
 */
class ReportService
{
    /** Statuses that count as a sold night. */
    public const OCCUPYING = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::CheckedOut];

    /** Statuses whose unpaid balance is still owed. */
    public const OWING = [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::CheckedOut];

    /**
     * @return array<string, mixed>
     */
    public function build(ReportPeriod $period): array
    {
        [$from, $end] = [$period->from->toDateString(), $period->end()->toDateString()];

        $occupancy = $this->occupancy($period);
        $revenue = $this->revenue($period);
        $outstanding = $this->outstanding($period);

        // Bookings by status, for stays that start in the period.
        $arriving = Booking::query()
            ->where('check_in', '>=', $from)->where('check_in', '<', $end)
            ->get(['id', 'cottage_id', 'status']);

        $byStatus = collect(BookingStatus::cases())
            ->mapWithKeys(fn (BookingStatus $s) => [$s->value => $arriving->where('status', $s)->count()])
            ->all();

        $cottages = $occupancy['units']->map(function (Cottage $cottage) use ($occupancy, $revenue, $arriving, $period) {
            $nights = $occupancy['by_cottage'][$cottage->id] ?? 0;

            return [
                'cottage' => $cottage,
                'stays' => $arriving->where('cottage_id', $cottage->id)
                    ->whereIn('status', [...self::OCCUPYING, BookingStatus::Pending])->count(),
                'nights' => $nights,
                'rate' => $this->percent($nights, $period->days()),
                'collected' => $revenue['by_cottage'][$cottage->id] ?? '0.00',
            ];
        })->sortByDesc(fn ($row) => [$row['nights'], $row['collected']])->values();

        return [
            'period' => $period,
            'bookings' => [
                'received' => Booking::where('created_at', '>=', $period->from)->where('created_at', '<', $period->end())->count(),
                'arriving' => $arriving->count(),
                'by_status' => $byStatus,
            ],
            'occupancy' => [
                'occupied' => $occupancy['occupied'],
                'available' => $occupancy['available'],
                'rate' => $this->percent($occupancy['occupied'], $occupancy['available']),
            ],
            'revenue' => $revenue,
            'outstanding' => $outstanding,
            'cottages' => $cottages,
            'daily' => collect($period->dates())->map(fn (CarbonImmutable $d) => [
                'date' => $d,
                'occupied' => $occupancy['by_day'][$d->toDateString()] ?? 0,
                'units' => $occupancy['units']->count(),
                'collected' => $revenue['by_day'][$d->toDateString()] ?? '0.00',
            ])->all(),
        ];
    }

    /**
     * Live numbers for the owner dashboard (not tied to a period).
     *
     * @return array<string, mixed>
     */
    public function today(): array
    {
        $today = CarbonImmutable::today()->toDateString();

        $owing = Booking::whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->get(['id', 'total_price', 'amount_paid']);

        $balance = '0.00';
        $owingCount = 0;
        foreach ($owing as $booking) {
            $due = Money::sub($booking->total_price, $booking->amount_paid);
            if (Money::isPositive($due)) {
                $balance = Money::add($balance, $due);
                $owingCount++;
            }
        }

        return [
            'pending' => Booking::where('status', BookingStatus::Pending)->count(),
            'arrivals' => Booking::where('status', BookingStatus::Confirmed)->where('check_in', $today)->count(),
            'in_house' => Booking::where('status', BookingStatus::CheckedIn)->count(),
            'departures' => Booking::where('status', BookingStatus::CheckedIn)->where('check_out', '<=', $today)->count(),
            'units' => Cottage::active()->count(),
            'owing_count' => $owingCount,
            'owing_total' => $balance,
        ];
    }

    // ------------------------------------------------------------------

    /**
     * @return array{occupied: int, available: int, by_day: array<string, int>, by_cottage: array<int, int>, units: Collection<int, Cottage>}
     */
    private function occupancy(ReportPeriod $period): array
    {
        [$from, $end] = [$period->from->toDateString(), $period->end()->toDateString()];

        $stays = Booking::query()
            ->whereIn('status', self::OCCUPYING)
            ->where('check_in', '<', $end)
            ->where('check_out', '>', $from)
            ->get(['id', 'cottage_id', 'check_in', 'check_out']);

        $byDay = [];
        $byCottage = [];

        foreach ($stays as $stay) {
            $night = max($stay->check_in->toDateString(), $from);
            $last = min($stay->check_out->toDateString(), $end);

            while ($night < $last) {
                $byDay[$night] = ($byDay[$night] ?? 0) + 1;
                $byCottage[$stay->cottage_id] = ($byCottage[$stay->cottage_id] ?? 0) + 1;
                $night = CarbonImmutable::parse($night)->addDay()->toDateString();
            }
        }

        $units = Cottage::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', array_keys($byCottage)))
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'capacity', 'price_per_night', 'is_active']);

        return [
            'occupied' => array_sum($byDay),
            'available' => $units->count() * $period->days(),
            'by_day' => $byDay,
            'by_cottage' => $byCottage,
            'units' => $units,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenue(ReportPeriod $period): array
    {
        $payments = Payment::query()
            ->with('booking:id,cottage_id')
            ->where('status', PaymentRecordStatus::Paid)
            ->where('paid_at', '>=', $period->from)
            ->where('paid_at', '<', $period->end())
            ->get(['id', 'booking_id', 'method', 'kind', 'amount', 'paid_at']);

        $received = '0.00';
        $refunded = '0.00';
        $byMethod = collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => '0.00'])->all();
        $byDay = [];
        $byCottage = [];

        foreach ($payments as $payment) {
            $signed = $payment->isRefund() ? Money::sub('0.00', $payment->amount) : Money::normalize($payment->amount);

            if ($payment->isRefund()) {
                $refunded = Money::add($refunded, $payment->amount);
            } else {
                $received = Money::add($received, $payment->amount);
            }

            $method = $payment->method->value;
            $day = $payment->paid_at->toDateString();
            $cottageId = $payment->booking->cottage_id;

            $byMethod[$method] = Money::add($byMethod[$method], $signed);
            $byDay[$day] = Money::add($byDay[$day] ?? '0.00', $signed);
            $byCottage[$cottageId] = Money::add($byCottage[$cottageId] ?? '0.00', $signed);
        }

        return [
            'received' => $received,
            'refunded' => $refunded,
            'net' => Money::sub($received, $refunded),
            'count' => $payments->reject->isRefund()->count(),
            'by_method' => $byMethod,
            'by_day' => $byDay,
            'by_cottage' => $byCottage,
        ];
    }

    /**
     * @return array{count: int, total: string, bookings: Collection<int, Booking>}
     */
    private function outstanding(ReportPeriod $period): array
    {
        $bookings = Booking::query()
            ->with(['user:id,name', 'cottage:id,name'])
            ->whereIn('status', self::OWING)
            ->where('check_in', '>=', $period->from->toDateString())
            ->where('check_in', '<', $period->end()->toDateString())
            ->orderBy('check_in')
            ->get()
            ->filter(fn (Booking $b) => Money::isPositive($b->balance()))
            ->values();

        $total = $bookings->reduce(fn (string $sum, Booking $b) => Money::add($sum, $b->balance()), '0.00');

        return ['count' => $bookings->count(), 'total' => $total, 'bookings' => $bookings];
    }

    /**
     * Chart series: one bucket per day, or per month for periods over ~2 months.
     *
     * @param  list<array{date: CarbonImmutable, occupied: int, units: int, collected: string}>  $daily
     * @return array{unit: string, buckets: list<array<string, mixed>>}
     */
    public function series(array $daily): array
    {
        $monthly = count($daily) > 62;

        $buckets = collect($daily)
            ->groupBy(fn ($d) => $d['date']->format($monthly ? 'Y-m' : 'Y-m-d'))
            ->map(function (Collection $days) use ($monthly) {
                $first = $days->first()['date'];
                $occupied = $days->sum('occupied');
                $available = $days->sum('units');

                return [
                    'label' => $monthly ? $first->format('M') : $first->format('j'),
                    'title' => $monthly ? $first->format('F Y') : $first->format('D, M j'),
                    'occupied' => $occupied,
                    'available' => $available,
                    'rate' => $this->percent($occupied, $available) ?? '0.0',
                    'collected' => $days->reduce(fn (string $sum, $d) => Money::add($sum, $d['collected']), '0.00'),
                    'weekend' => ! $monthly && $first->isWeekend(),
                ];
            })
            ->values();

        $max = $buckets->reduce(fn (string $m, $b) => Money::max($m, $b['collected']), '0.00');

        return [
            'unit' => $monthly ? 'month' : 'day',
            'max_collected' => $max,
            'buckets' => $buckets->map(fn ($b) => $b + [
                // bar height as % of the busiest bucket (refund-only days draw no bar)
                'collected_height' => Money::isPositive($max) && Money::isPositive($b['collected'])
                    ? bcdiv(bcmul($b['collected'], '100', 2), $max, 1)
                    : '0',
            ])->all(),
        ];
    }

    /** part ÷ whole as a percentage with one decimal, rounded half up; null when whole is 0. */
    public function percent(int $part, int $whole): ?string
    {
        if ($whole <= 0) {
            return null;
        }

        return bcdiv(bcadd(bcdiv(bcmul((string) $part, '1000', 0), (string) $whole, 1), '0.5', 1), '10', 1);
    }
}
