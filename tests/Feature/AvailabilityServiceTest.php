<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\DatesUnavailableException;
use App\Exceptions\InvalidStayException;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Existing booking used throughout: 2026-12-10 → 2026-12-13
 * i.e. it holds the nights of Dec 10, 11, and 12. Dec 13 is free from 2:00 PM.
 */
class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        $this->service = app(AvailabilityService::class);
        $this->cottage = Cottage::factory()->create(['capacity' => 4, 'price_per_night' => '2500.50']);
    }

    private function book(string $in, string $out, BookingStatus $status = BookingStatus::Pending, ?Cottage $cottage = null): Booking
    {
        return Booking::factory()
            ->for($cottage ?? $this->cottage)
            ->stay($in, $out)
            ->status($status)
            ->create();
    }

    // ------------------------------------------------------------------
    // Half-open interval + changeover
    // ------------------------------------------------------------------

    public function test_free_when_there_are_no_bookings(): void
    {
        $this->assertTrue($this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-13'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function overlapCases(): array
    {
        return [
            // requested            in            out           available?
            'identical dates' => ['2026-12-10', '2026-12-13', false],
            'overlaps the start' => ['2026-12-08', '2026-12-11', false],
            'overlaps the end' => ['2026-12-12', '2026-12-15', false],
            'inside existing' => ['2026-12-11', '2026-12-12', false],
            'surrounds existing' => ['2026-12-09', '2026-12-14', false],
            'same check-in, shorter' => ['2026-12-10', '2026-12-11', false],
            'last held night only' => ['2026-12-12', '2026-12-13', false],
            'changeover: starts on existing check-out' => ['2026-12-13', '2026-12-15', true],
            'changeover: ends on existing check-in' => ['2026-12-07', '2026-12-10', true],
            'entirely before' => ['2026-12-01', '2026-12-05', true],
            'entirely after' => ['2026-12-20', '2026-12-22', true],
        ];
    }

    #[DataProvider('overlapCases')]
    public function test_half_open_overlap_rules(string $in, string $out, bool $expected): void
    {
        $this->book('2026-12-10', '2026-12-13');

        $this->assertSame($expected, $this->service->isAvailable($this->cottage, $in, $out));
    }

    public function test_back_to_back_changeovers_on_both_sides(): void
    {
        $this->book('2026-12-07', '2026-12-10');
        $this->book('2026-12-13', '2026-12-16');

        $this->assertTrue($this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-13'));
        $this->assertFalse($this->service->isAvailable($this->cottage, '2026-12-09', '2026-12-13'));
        $this->assertFalse($this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-14'));
    }

    public function test_a_stay_needs_at_least_one_night(): void
    {
        $this->assertFalse($this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-10'));
        $this->assertFalse($this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-09'));
    }

    // ------------------------------------------------------------------
    // Statuses
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: BookingStatus, 1: bool}>
     */
    public static function statuses(): array
    {
        return [
            'pending holds dates' => [BookingStatus::Pending, false],
            'confirmed holds dates' => [BookingStatus::Confirmed, false],
            'checked in holds dates' => [BookingStatus::CheckedIn, false],
            'rejected releases dates' => [BookingStatus::Rejected, true],
            'cancelled releases dates' => [BookingStatus::Cancelled, true],
            'checked out releases dates' => [BookingStatus::CheckedOut, true],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_blocking_statuses_hold_dates(BookingStatus $status, bool $expected): void
    {
        $this->book('2026-12-10', '2026-12-13', $status);

        $this->assertSame($expected, $this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-13'));
    }

    public function test_bookings_of_other_units_do_not_block(): void
    {
        $this->book('2026-12-10', '2026-12-13', cottage: Cottage::factory()->create());

        $this->assertTrue($this->service->isAvailable($this->cottage, '2026-12-10', '2026-12-13'));
    }

    public function test_a_booking_can_ignore_itself_when_being_changed(): void
    {
        $booking = $this->book('2026-12-10', '2026-12-13');

        $this->assertFalse($this->service->isAvailable($this->cottage, '2026-12-11', '2026-12-14'));
        $this->assertTrue($this->service->isAvailable($this->cottage, '2026-12-11', '2026-12-14', $booking->id));
    }

    // ------------------------------------------------------------------
    // Search + calendar
    // ------------------------------------------------------------------

    public function test_available_cottages_filters_by_dates_capacity_and_listing(): void
    {
        $this->book('2026-12-10', '2026-12-13'); // $this->cottage (cap 4) is taken

        $free = Cottage::factory()->create(['name' => 'Free Villa', 'capacity' => 8]);
        $tooSmall = Cottage::factory()->create(['name' => 'Tiny', 'capacity' => 2]);
        $delisted = Cottage::factory()->delisted()->create(['capacity' => 8]);
        $releasedOnly = Cottage::factory()->create(['name' => 'Released', 'capacity' => 6]);
        $this->book('2026-12-10', '2026-12-13', BookingStatus::Cancelled, $releasedOnly);

        $ids = $this->service->availableCottages('2026-12-11', '2026-12-12', 3)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$free->id, $releasedOnly->id], $ids);
        $this->assertNotContains($tooSmall->id, $ids);
        $this->assertNotContains($delisted->id, $ids);
    }

    public function test_changeover_day_shows_unit_as_available_in_search(): void
    {
        $this->book('2026-12-10', '2026-12-13');

        $ids = $this->service->availableCottages('2026-12-13', '2026-12-14')->pluck('id');

        $this->assertContains($this->cottage->id, $ids);
    }

    public function test_booked_nights_lists_held_nights_inside_the_window(): void
    {
        $this->book('2026-11-29', '2026-12-02');                            // spills into window
        $this->book('2026-12-10', '2026-12-12', BookingStatus::Confirmed);
        $this->book('2026-12-20', '2026-12-22', BookingStatus::Cancelled);  // released

        $this->assertSame(
            ['2026-12-01', '2026-12-10', '2026-12-11'],
            $this->service->bookedNights($this->cottage, '2026-12-01', '2027-01-01'),
        );
    }

    // ------------------------------------------------------------------
    // Locking reservation
    // ------------------------------------------------------------------

    public function test_reserve_runs_callback_when_dates_are_free(): void
    {
        $booking = $this->service->reserve($this->cottage, '2026-12-10', '2026-12-13', fn (Cottage $c) => $this->book('2026-12-10', '2026-12-13', cottage: $c));

        $this->assertModelExists($booking);
    }

    public function test_reserve_refuses_taken_dates_and_creates_nothing(): void
    {
        $this->book('2026-12-10', '2026-12-13');
        $called = false;

        try {
            $this->service->reserve($this->cottage, '2026-12-12', '2026-12-14', function () use (&$called) {
                $called = true;
            });
            $this->fail('Expected DatesUnavailableException.');
        } catch (DatesUnavailableException) {
            // expected
        }

        $this->assertFalse($called);
        $this->assertSame(1, Booking::count());
    }

    public function test_second_of_two_sequential_reservations_for_same_dates_fails(): void
    {
        $create = fn (Cottage $c) => $this->book('2026-12-10', '2026-12-13', cottage: $c);

        $this->service->reserve($this->cottage, '2026-12-10', '2026-12-13', $create);

        $this->expectException(DatesUnavailableException::class);
        $this->service->reserve($this->cottage, '2026-12-10', '2026-12-13', $create);
    }

    public function test_reserve_rolls_back_if_callback_fails(): void
    {
        try {
            $this->service->reserve($this->cottage, '2026-12-10', '2026-12-13', function (Cottage $c) {
                $this->book('2026-12-10', '2026-12-13', cottage: $c);
                throw new \RuntimeException('payment gateway down');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, Booking::count());
    }

    // ------------------------------------------------------------------
    // Pricing (bcmath)
    // ------------------------------------------------------------------

    public function test_quote_is_rate_times_nights_without_float_error(): void
    {
        $this->assertSame(3, $this->service->nights('2026-12-10', '2026-12-13'));
        $this->assertSame('7501.50', $this->service->quote($this->cottage, '2026-12-10', '2026-12-13'));

        $cheap = Cottage::factory()->create(['price_per_night' => '0.10']);
        $this->assertSame('0.30', $this->service->quote($cheap, '2026-12-10', '2026-12-13'));
    }

    public function test_quote_spans_month_and_year_boundaries(): void
    {
        $this->assertSame(4, $this->service->nights('2026-12-30', '2027-01-03'));
        $this->assertSame('10002.00', $this->service->quote($this->cottage, '2026-12-30', '2027-01-03'));
    }

    public function test_payment_status_from_total_and_paid(): void
    {
        $this->assertSame(PaymentStatus::Unpaid, $this->service->paymentStatus('7501.50', '0.00'));
        $this->assertSame(PaymentStatus::PartiallyPaid, $this->service->paymentStatus('7501.50', '3750.75'));
        $this->assertSame(PaymentStatus::PartiallyPaid, $this->service->paymentStatus('7501.50', '7501.49'));
        $this->assertSame(PaymentStatus::Paid, $this->service->paymentStatus('7501.50', '7501.50'));
        $this->assertSame(PaymentStatus::Paid, $this->service->paymentStatus('7501.50', '8000.00'));
    }

    // ------------------------------------------------------------------
    // Date rules
    // ------------------------------------------------------------------

    public function test_validate_stay_accepts_today_and_returns_dates(): void
    {
        [$in, $out] = $this->service->validateStay('2026-11-01', '2026-11-02');

        $this->assertSame('2026-11-01', $in->toDateString());
        $this->assertSame('2026-11-02', $out->toDateString());
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function invalidStays(): array
    {
        return [
            'missing check-out' => ['2026-12-10', null, 'both'],
            'past check-in' => ['2026-10-31', '2026-11-02', 'past'],
            'same day' => ['2026-12-10', '2026-12-10', 'at least one day'],
            'reversed' => ['2026-12-12', '2026-12-10', 'at least one day'],
            'too long' => ['2026-12-01', '2027-01-15', 'limited to 30 nights'],
            'beyond window' => ['2027-11-05', '2027-11-06', '365 days'],
            'impossible date' => ['2027-02-30', '2027-03-02', 'valid'],
            'garbage' => ['tomorrow-ish', '2026-12-10', 'valid'],
        ];
    }

    #[DataProvider('invalidStays')]
    public function test_validate_stay_rejects_bad_dates(?string $in, ?string $out, string $messageFragment): void
    {
        $this->expectException(InvalidStayException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($messageFragment, '/').'/i');

        $this->service->validateStay($in, $out);
    }

    // ------------------------------------------------------------------
    // Storage + integrity
    // ------------------------------------------------------------------

    public function test_dates_are_stored_as_plain_dates(): void
    {
        $booking = $this->book('2026-12-10', '2026-12-13');

        $row = DB::table('bookings')->where('id', $booking->id)->first();

        $this->assertSame('2026-12-10', $row->check_in);
        $this->assertSame('2026-12-13', $row->check_out);
        $this->assertSame(3, $booking->fresh()->nights());
    }

    public function test_a_booked_cottage_cannot_be_deleted_only_delisted(): void
    {
        $this->book('2026-12-10', '2026-12-13', BookingStatus::CheckedOut);

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/cottages/{$this->cottage->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($this->cottage);
    }
}
