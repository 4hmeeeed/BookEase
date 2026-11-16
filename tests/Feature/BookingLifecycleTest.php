<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 09:00:00');
        $this->staff = User::factory()->staff()->create();
        $this->cottage = Cottage::factory()->create();
    }

    private function booking(BookingStatus $status = BookingStatus::Pending, string $in = '2026-12-10', string $out = '2026-12-13'): Booking
    {
        return Booking::factory()->for($this->cottage)->stay($in, $out)->status($status)->create();
    }

    private function datesFree(string $in = '2026-12-10', string $out = '2026-12-13'): bool
    {
        return app(AvailabilityService::class)->isAvailable($this->cottage, $in, $out);
    }

    // ---------------- state machine ----------------

    /**
     * @return array<string, array{0: BookingStatus, 1: list<BookingStatus>}>
     */
    public static function machine(): array
    {
        return [
            'pending' => [BookingStatus::Pending, [BookingStatus::Confirmed, BookingStatus::Rejected, BookingStatus::Cancelled]],
            'confirmed' => [BookingStatus::Confirmed, [BookingStatus::CheckedIn, BookingStatus::Cancelled]],
            'checked in' => [BookingStatus::CheckedIn, [BookingStatus::CheckedOut]],
            'rejected' => [BookingStatus::Rejected, []],
            'cancelled' => [BookingStatus::Cancelled, []],
            'checked out' => [BookingStatus::CheckedOut, []],
        ];
    }

    /**
     * @param  list<BookingStatus>  $allowed
     */
    #[DataProvider('machine')]
    public function test_state_machine(BookingStatus $from, array $allowed): void
    {
        foreach (BookingStatus::cases() as $to) {
            $this->assertSame(in_array($to, $allowed, true), $from->canTransitionTo($to), "{$from->value} → {$to->value}");
        }
    }

    // ---------------- staff actions over HTTP ----------------

    public function test_staff_can_approve_a_pending_booking(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->staff)
            ->post("/staff/bookings/{$booking->reference_code}/approve")
            ->assertSessionHas('status');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertFalse($this->datesFree(), 'confirmed booking keeps holding its dates');

        $log = $booking->statusChanges()->first();
        $this->assertSame(BookingStatus::Pending, $log->from_status);
        $this->assertSame(BookingStatus::Confirmed, $log->to_status);
        $this->assertSame($this->staff->id, $log->user_id);
    }

    public function test_owner_can_also_work_the_front_desk(): void
    {
        $booking = $this->booking();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/staff/bookings/{$booking->reference_code}/approve");

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_reject_requires_a_reason_and_releases_dates(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->staff)
            ->post("/staff/bookings/{$booking->reference_code}/reject", ['remarks' => ''])
            ->assertSessionHasErrors('remarks');
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);

        $this->actingAs($this->staff)
            ->post("/staff/bookings/{$booking->reference_code}/reject", ['remarks' => 'Unit under repair those dates.']);

        $this->assertSame(BookingStatus::Rejected, $booking->fresh()->status);
        $this->assertTrue($this->datesFree());
        $this->assertSame('Unit under repair those dates.', $booking->statusChanges()->first()->remarks);
    }

    public function test_invalid_transition_shows_error_and_changes_nothing(): void
    {
        $booking = $this->booking(BookingStatus::Confirmed);

        $this->actingAs($this->staff)
            ->post("/staff/bookings/{$booking->reference_code}/approve")
            ->assertSessionHas('error');

        $this->actingAs($this->staff)
            ->post("/staff/bookings/{$booking->reference_code}/check-out")
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(0, $booking->statusChanges()->count());
    }

    public function test_check_in_only_from_the_check_in_day(): void
    {
        $booking = $this->booking(BookingStatus::Confirmed);

        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/check-in")->assertSessionHas('error');
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        $this->travelTo('2026-12-10 14:05:00');
        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/check-in")->assertSessionHas('status');
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
        $this->assertFalse($this->datesFree(), 'checked-in booking holds its dates');
    }

    public function test_late_arrival_can_still_check_in_before_check_out_day(): void
    {
        $booking = $this->booking(BookingStatus::Confirmed);

        $this->travelTo('2026-12-12 20:00:00');
        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/check-in");

        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
    }

    public function test_no_check_in_after_the_stay_has_ended(): void
    {
        $booking = $this->booking(BookingStatus::Confirmed);

        $this->travelTo('2026-12-13 10:00:00');
        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/check-in")->assertSessionHas('error');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_a_same_day_request_can_be_approved_on_its_check_in_day(): void
    {
        $booking = $this->booking();

        $this->travelTo('2026-12-10 15:00:00');
        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/approve")->assertSessionHas('status');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_a_request_whose_stay_has_ended_cannot_be_approved(): void
    {
        // Approving it would create a confirmed stay that can never be checked in.
        $booking = $this->booking();

        $this->travelTo('2026-12-13 10:00:00');
        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/approve")->assertSessionHas('error');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_check_out_releases_the_dates(): void
    {
        $booking = $this->booking(BookingStatus::CheckedIn);

        $this->actingAs($this->staff)->post("/staff/bookings/{$booking->reference_code}/check-out");

        $this->assertSame(BookingStatus::CheckedOut, $booking->fresh()->status);
        $this->assertTrue($this->datesFree());
    }

    public function test_staff_cancel_requires_reason_and_releases_dates(): void
    {
        $booking = $this->booking(BookingStatus::Confirmed);

        $this->actingAs($this->staff)
            ->post("/staff/bookings/{$booking->reference_code}/cancel", ['remarks' => 'Guest called to cancel.']);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertTrue($this->datesFree());
    }

    public function test_guests_cannot_use_staff_actions(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->for($this->cottage)->for($guest)->stay('2026-12-10', '2026-12-13')->create();

        $this->actingAs($guest)->post("/staff/bookings/{$booking->reference_code}/approve")->assertForbidden();
        $this->actingAs($guest)->get("/staff/bookings/{$booking->reference_code}")->assertForbidden();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    // ---------------- service-level ----------------

    public function test_service_rejects_final_state_changes(): void
    {
        $booking = $this->booking(BookingStatus::CheckedOut);

        $this->expectException(InvalidBookingTransitionException::class);
        app(BookingLifecycleService::class)->cancel($booking, $this->staff, 'x');
    }

    public function test_full_happy_path_leaves_complete_audit_trail(): void
    {
        $svc = app(BookingLifecycleService::class);
        $booking = $this->booking();

        $svc->approve($booking, $this->staff);
        $this->travelTo('2026-12-10 14:00:00');
        $svc->checkIn($booking, $this->staff);
        $this->travelTo('2026-12-13 11:30:00');
        $svc->checkOut($booking, $this->staff);

        $this->assertSame(BookingStatus::CheckedOut, $booking->fresh()->status);
        $this->assertSame(
            ['checked_out', 'checked_in', 'confirmed'],
            $booking->statusChanges()->pluck('to_status')->map->value->all(),
        );
    }

    // ---------------- pages ----------------

    public function test_queue_and_detail_pages_render(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->staff)->get('/staff')->assertOk()->assertSee($booking->reference_code);
        $this->actingAs($this->staff)->get('/staff/bookings')->assertOk()->assertSee($booking->reference_code);
        $this->actingAs($this->staff)->get('/staff/bookings?tab=history')->assertOk()->assertDontSee($booking->reference_code);
        $this->actingAs($this->staff)->get("/staff/bookings/{$booking->reference_code}")
            ->assertOk()->assertSee('Approve')->assertSee('Reject');
    }

    public function test_a_confirmed_guest_who_never_arrived_stays_in_the_arrivals_queue(): void
    {
        // The stay was Dec 10–13 and the guest never came. Staff must still find
        // it to cancel it; otherwise it counts as a sold stay and an unpaid balance forever.
        $noShow = $this->booking(BookingStatus::Confirmed);
        $this->travelTo('2026-12-14 09:00:00');

        $this->actingAs($this->staff)->get('/staff/bookings?tab=arrivals')->assertOk()->assertSee($noShow->reference_code);
        $this->actingAs($this->staff)->get('/staff')->assertOk()->assertSee($noShow->reference_code);
    }

    public function test_queue_search_by_reference_or_guest(): void
    {
        $alice = Booking::factory()->for($this->cottage)->for(User::factory()->create(['name' => 'Alice Reyes']))->stay('2026-12-10', '2026-12-12')->create();
        $bob = Booking::factory()->for($this->cottage)->for(User::factory()->create(['name' => 'Bob Santos']))->stay('2026-12-20', '2026-12-22')->create();

        $this->actingAs($this->staff)->get('/staff/bookings?tab=all&q=Alice')
            ->assertSee($alice->reference_code)->assertDontSee($bob->reference_code);
        $this->actingAs($this->staff)->get("/staff/bookings?tab=all&q={$bob->reference_code}")
            ->assertSee('Bob Santos')->assertDontSee('Alice Reyes');
    }
}
