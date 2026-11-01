<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Report figures checked against a hand-computed November 2026 scenario.
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;

    private Cottage $a;

    private Cottage $b;

    private Cottage $d;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 10:00:00');
        $this->reports = app(ReportService::class);

        $this->a = Cottage::factory()->create(['name' => 'A Cottage', 'price_per_night' => '2000.00']);
        $this->b = Cottage::factory()->create(['name' => 'B Villa', 'price_per_night' => '3000.00']);
        Cottage::factory()->create(['name' => 'C Delisted, never used', 'is_active' => false]);
        $this->d = Cottage::factory()->create(['name' => 'D Delisted later', 'price_per_night' => '1500.00']);
    }

    private function stay(Cottage $cottage, string $in, string $out, BookingStatus $status, string $total, string $paid = '0.00'): Booking
    {
        return Booking::factory()->for($cottage)->stay($in, $out)->create([
            'status' => $status, 'total_price' => $total, 'amount_paid' => $paid,
        ]);
    }

    private function pay(Booking $booking, PaymentMethod $method, PaymentKind $kind, string $amount, string $paidAt, PaymentRecordStatus $status = PaymentRecordStatus::Paid): Payment
    {
        return Payment::factory()->for($booking)->create([
            'method' => $method, 'kind' => $kind, 'amount' => $amount, 'status' => $status,
            'paid_at' => $status === PaymentRecordStatus::Paid ? $paidAt : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function november(): array
    {
        $aSpill = $this->stay($this->a, '2026-10-30', '2026-11-03', BookingStatus::Confirmed, '8000.00', '8000.00'); // 2 nights in Nov
        $aDone = $this->stay($this->a, '2026-11-10', '2026-11-12', BookingStatus::CheckedOut, '4000.00', '4000.00');   // 2
        $this->stay($this->a, '2026-11-20', '2026-11-22', BookingStatus::Pending, '4000.00');                          // not occupied, owes 4000
        $bIn = $this->stay($this->b, '2026-11-14', '2026-11-16', BookingStatus::CheckedIn, '6000.00', '3000.00');      // 2, owes 3000
        $this->stay($this->b, '2026-11-05', '2026-11-08', BookingStatus::Cancelled, '9000.00');                        // ignored
        $this->stay($this->b, '2026-11-29', '2026-12-02', BookingStatus::Confirmed, '9000.00', '9000.00');             // 2 nights in Nov
        $this->stay($this->d, '2026-11-25', '2026-11-26', BookingStatus::Confirmed, '1500.00');                        // 1, owes 1500
        $this->d->update(['is_active' => false]);

        $this->pay($bIn, PaymentMethod::GCash, PaymentKind::Down, '3000.50', '2026-11-14 15:00:00');
        $this->pay($aDone, PaymentMethod::Cash, PaymentKind::Full, '2000.25', '2026-11-10 14:00:00');
        $this->pay($aDone, PaymentMethod::Cash, PaymentKind::Refund, '500.10', '2026-11-11 09:00:00');
        $this->pay($bIn, PaymentMethod::Maya, PaymentKind::Balance, '1000.00', '', PaymentRecordStatus::Pending);   // not settled
        $this->pay($aSpill, PaymentMethod::GCash, PaymentKind::Full, '999.00', '2026-10-31 23:59:59');               // before period
        $this->pay($aSpill, PaymentMethod::GCash, PaymentKind::Full, '111.00', '2026-12-01 00:00:00');               // after period

        return $this->reports->build(ReportPeriod::between('2026-11-01', '2026-11-30'));
    }

    public function test_occupancy_counts_only_sold_nights_inside_the_period(): void
    {
        $r = $this->november();

        $this->assertSame(9, $r['occupancy']['occupied']);
        $this->assertSame(90, $r['occupancy']['available']); // A, B, and D (occupied before delisting) × 30
        $this->assertSame('10.0', $r['occupancy']['rate']);

        $daily = collect($r['daily'])->keyBy(fn ($d) => $d['date']->toDateString());
        $this->assertSame(1, $daily['2026-11-01']['occupied']);
        $this->assertSame(1, $daily['2026-11-02']['occupied']);
        $this->assertSame(0, $daily['2026-11-03']['occupied']); // check-out day is free
        $this->assertSame(0, $daily['2026-11-06']['occupied']); // cancelled
        $this->assertSame(0, $daily['2026-11-20']['occupied']); // pending
        $this->assertSame(1, $daily['2026-11-30']['occupied']);
    }

    public function test_collected_money_is_settled_payments_in_the_period_minus_refunds(): void
    {
        $r = $this->november();

        $this->assertSame('5000.75', $r['revenue']['received']);
        $this->assertSame('500.10', $r['revenue']['refunded']);
        $this->assertSame('4500.65', $r['revenue']['net']);
        $this->assertSame(2, $r['revenue']['count']);
        $this->assertSame('3000.50', $r['revenue']['by_method']['gcash']);
        $this->assertSame('1500.15', $r['revenue']['by_method']['cash']);
        $this->assertSame('0.00', $r['revenue']['by_method']['maya']);

        $daily = collect($r['daily'])->keyBy(fn ($d) => $d['date']->toDateString());
        $this->assertSame('-500.10', $daily['2026-11-11']['collected']);
        $this->assertSame('3000.50', $daily['2026-11-14']['collected']);
    }

    public function test_outstanding_balances_for_active_stays_starting_in_the_period(): void
    {
        $r = $this->november();

        $this->assertSame(3, $r['outstanding']['count']);
        $this->assertSame('8500.00', $r['outstanding']['total']);
        $this->assertSame(['2026-11-14', '2026-11-20', '2026-11-25'],
            $r['outstanding']['bookings']->map(fn ($b) => $b->check_in->toDateString())->all());
    }

    public function test_bookings_by_status_and_unit_performance(): void
    {
        $r = $this->november();

        $this->assertSame(6, $r['bookings']['arriving']);
        $this->assertSame(7, $r['bookings']['received']);
        $this->assertSame(2, $r['bookings']['by_status']['confirmed']);
        $this->assertSame(1, $r['bookings']['by_status']['cancelled']);
        $this->assertSame(0, $r['bookings']['by_status']['rejected']);

        $rows = $r['cottages']->keyBy(fn ($row) => $row['cottage']->name);
        $this->assertSame(['A Cottage', 'B Villa', 'D Delisted later'], $rows->keys()->sort()->values()->all());
        $this->assertSame(4, $rows['A Cottage']['nights']);
        $this->assertSame('13.3', $rows['A Cottage']['rate']);
        $this->assertSame('1500.15', $rows['A Cottage']['collected']);
        $this->assertSame(4, $rows['B Villa']['nights']);
        $this->assertSame('3000.50', $rows['B Villa']['collected']);
        $this->assertSame(1, $rows['D Delisted later']['nights']);
    }

    public function test_empty_period_has_no_division_by_zero(): void
    {
        Cottage::query()->update(['is_active' => false]);

        $r = $this->reports->build(ReportPeriod::between('2026-11-01', '2026-11-30'));

        $this->assertSame(0, $r['occupancy']['available']);
        $this->assertNull($r['occupancy']['rate']);
        $this->assertSame('0.00', $r['revenue']['net']);
    }

    public function test_percent_rounds_half_up_to_one_decimal(): void
    {
        $this->assertSame('33.3', $this->reports->percent(1, 3));
        $this->assertSame('66.7', $this->reports->percent(2, 3));
        $this->assertSame('12.5', $this->reports->percent(1, 8));
        $this->assertSame('100.0', $this->reports->percent(5, 5));
        $this->assertSame('0.0', $this->reports->percent(0, 5));
        $this->assertNull($this->reports->percent(3, 0));
    }

    public function test_series_switches_to_months_for_long_periods(): void
    {
        $month = $this->reports->series($this->reports->build(ReportPeriod::preset('this_month'))['daily']);
        $this->assertSame('day', $month['unit']);
        $this->assertCount(30, $month['buckets']);

        $year = $this->reports->series($this->reports->build(ReportPeriod::preset('this_year'))['daily']);
        $this->assertSame('month', $year['unit']);
        $this->assertCount(12, $year['buckets']);
        $this->assertSame('January 2026', $year['buckets'][0]['title']);
    }

    public function test_report_period_parsing(): void
    {
        $last = ReportPeriod::preset('last_month');
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31'], $last->query());
        $this->assertSame(31, $last->days());

        $swapped = ReportPeriod::between('2026-11-30', '2026-11-01');
        $this->assertSame('2026-11-01', $swapped->from->toDateString());

        $huge = ReportPeriod::between('2020-01-01', '2026-01-01');
        $this->assertSame(ReportPeriod::MAX_DAYS, $huge->days());

        $bad = ReportPeriod::fromRequest(Request::create('/', 'GET', ['from' => '2026-02-30', 'to' => 'x']));
        $this->assertSame('this_month', $bad->preset);

        $custom = ReportPeriod::fromRequest(Request::create('/', 'GET', ['from' => '2026-11-05', 'to' => '2026-11-09']));
        $this->assertSame('custom', $custom->preset);
        $this->assertSame(5, $custom->days());
        $this->assertSame('Nov 5 – Nov 9, 2026', $custom->label());
    }
}
