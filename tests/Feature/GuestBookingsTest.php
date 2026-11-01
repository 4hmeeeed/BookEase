<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBookingsTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 09:00:00');
        $this->guest = User::factory()->create();
        $this->cottage = Cottage::factory()->create(['name' => 'Seaside Cottage']);
    }

    private function myBooking(BookingStatus $status = BookingStatus::Pending, ?User $owner = null): Booking
    {
        return Booking::factory()
            ->for($this->cottage)
            ->for($owner ?? $this->guest)
            ->stay('2026-12-10', '2026-12-13')
            ->status($status)
            ->create();
    }

    public function test_guest_sees_only_their_own_bookings(): void
    {
        $mine = $this->myBooking();
        $theirs = Booking::factory()->for($this->cottage)->stay('2026-12-20', '2026-12-21')->create();

        $this->actingAs($this->guest)->get('/my/bookings')
            ->assertOk()
            ->assertSee($mine->reference_code)
            ->assertDontSee($theirs->reference_code);

        $this->actingAs($this->guest)->get("/my/bookings/{$mine->reference_code}")->assertOk()->assertSee('Seaside Cottage');
        $this->actingAs($this->guest)->get("/my/bookings/{$theirs->reference_code}")->assertForbidden();
    }

    public function test_guest_dashboard_shows_next_stay(): void
    {
        $booking = $this->myBooking(BookingStatus::Confirmed);

        $this->actingAs($this->guest)->get('/my')
            ->assertOk()
            ->assertSee('Your next stay')
            ->assertSee($booking->reference_code)
            ->assertSee('9 days to go');
    }

    public function test_guest_can_cancel_before_check_in_and_dates_are_released(): void
    {
        $booking = $this->myBooking(BookingStatus::Confirmed);

        $this->actingAs($this->guest)
            ->post("/my/bookings/{$booking->reference_code}/cancel", ['remarks' => 'Change of plans'])
            ->assertRedirect("/my/bookings/{$booking->reference_code}")
            ->assertSessionHas('status');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertTrue(app(AvailabilityService::class)->isAvailable($this->cottage, '2026-12-10', '2026-12-13'));
        $this->assertSame($this->guest->id, $booking->statusChanges()->first()->user_id);
    }

    public function test_guest_cannot_cancel_on_or_after_check_in_day(): void
    {
        $booking = $this->myBooking(BookingStatus::Confirmed);
        $this->travelTo('2026-12-10 08:00:00');

        $this->actingAs($this->guest)->get("/my/bookings/{$booking->reference_code}")->assertDontSee('Cancel this booking');
        $this->actingAs($this->guest)->post("/my/bookings/{$booking->reference_code}/cancel")->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_guest_cannot_cancel_a_checked_in_or_rejected_booking(): void
    {
        foreach ([BookingStatus::CheckedIn, BookingStatus::Rejected] as $status) {
            $booking = Booking::factory()->for($this->cottage)->for($this->guest)
                ->stay('2026-12-20', '2026-12-21')->status($status)->create();

            $this->actingAs($this->guest)->post("/my/bookings/{$booking->reference_code}/cancel")->assertForbidden();
            $this->assertSame($status, $booking->fresh()->status);
        }
    }

    public function test_guest_cannot_cancel_someone_elses_booking(): void
    {
        $other = $this->myBooking(owner: User::factory()->create());

        $this->actingAs($this->guest)->post("/my/bookings/{$other->reference_code}/cancel")->assertForbidden();

        $this->assertSame(BookingStatus::Pending, $other->fresh()->status);
    }

    public function test_booking_pages_render_for_every_status(): void
    {
        $staff = User::factory()->staff()->create();

        foreach (BookingStatus::cases() as $i => $status) {
            $booking = Booking::factory()->for($this->cottage)->for($this->guest)
                ->stay(sprintf('2027-01-%02d', $i + 1), sprintf('2027-01-%02d', $i + 2))->status($status)->create();
            $booking->statusChanges()->create(['user_id' => $staff->id, 'from_status' => null, 'to_status' => $status, 'remarks' => 'note']);

            $this->actingAs($this->guest)->get("/my/bookings/{$booking->reference_code}")->assertOk()->assertSee($status->label());
            $this->actingAs($staff)->get("/staff/bookings/{$booking->reference_code}")->assertOk()->assertSee('Audit trail');
        }

        $this->actingAs($this->guest)->get('/my/bookings')->assertOk();
        $this->actingAs($staff)->get('/staff/bookings?tab=all')->assertOk();
    }

    public function test_calendar_reserve_button_targets_reservation_form(): void
    {
        $this->actingAs($this->guest)->get("/cottages/{$this->cottage->id}")
            ->assertOk()
            ->assertSee('Reserve these dates');

        $this->actingAs(User::factory()->staff()->create())->get("/cottages/{$this->cottage->id}")
            ->assertOk()
            ->assertSee("Staff accounts can't make online reservations", false);
    }
}
