<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        $this->guest = User::factory()->create(['phone' => null]);
        $this->cottage = Cottage::factory()->create(['name' => 'Seaside Cottage', 'capacity' => 4, 'price_per_night' => '2500.50']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'check_in' => '2026-12-10',
            'check_out' => '2026-12-13',
            'guests_count' => 3,
            'phone' => '+63 917-123-4567',
            'notes' => 'Arriving around 3 PM.',
            'agree' => '1',
        ], $overrides);
    }

    private function bookUrl(string $in = '2026-12-10', string $out = '2026-12-13'): string
    {
        return "/cottages/{$this->cottage->id}/book?check_in={$in}&check_out={$out}";
    }

    // ---------------- form ----------------

    public function test_guest_sees_reservation_form_with_server_total(): void
    {
        $this->actingAs($this->guest)->get($this->bookUrl())
            ->assertOk()
            ->assertSee('Confirm your reservation')
            ->assertSee('₱7,501.50');
    }

    public function test_visitors_must_log_in_first(): void
    {
        $this->get($this->bookUrl())->assertRedirect('/login');
    }

    public function test_staff_cannot_make_online_reservations(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get($this->bookUrl())->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post("/cottages/{$this->cottage->id}/book", $this->payload())->assertForbidden();
    }

    public function test_form_bounces_back_when_dates_are_taken(): void
    {
        Booking::factory()->for($this->cottage)->stay('2026-12-11', '2026-12-12')->create();

        $this->actingAs($this->guest)->get($this->bookUrl())
            ->assertRedirect(route('cottages.show', $this->cottage))
            ->assertSessionHas('error');
    }

    public function test_form_bounces_back_for_invalid_dates(): void
    {
        $this->actingAs($this->guest)->get($this->bookUrl('2026-10-01', '2026-10-03'))
            ->assertRedirect(route('cottages.show', $this->cottage))
            ->assertSessionHas('error');
    }

    public function test_delisted_unit_cannot_be_booked(): void
    {
        $this->cottage->update(['is_active' => false]);

        $this->actingAs($this->guest)->get($this->bookUrl())->assertNotFound();
    }

    // ---------------- submit ----------------

    public function test_guest_can_reserve_and_booking_is_pending_with_reference(): void
    {
        $response = $this->actingAs($this->guest)
            ->post("/cottages/{$this->cottage->id}/book", $this->payload());

        $booking = Booking::sole();

        $response->assertRedirect(route('guest.bookings.show', $booking))->assertSessionHas('status');

        $this->assertSame($this->guest->id, $booking->user_id);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->payment_status);
        $this->assertSame('7501.50', $booking->total_price);      // 3 × 2500.50, bcmath
        $this->assertSame('0.00', $booking->amount_paid);
        $this->assertSame(3, $booking->guests_count);
        $this->assertSame('Arriving around 3 PM.', $booking->notes);
        $this->assertMatchesRegularExpression('/^LBN-[2-9A-HJKMNP-TV-Z]{6}$/', $booking->reference_code);
        $this->assertSame('09171234567', $this->guest->fresh()->phone);

        $log = $booking->statusChanges()->sole();
        $this->assertNull($log->from_status);
        $this->assertSame(BookingStatus::Pending, $log->to_status);
        $this->assertSame($this->guest->id, $log->user_id);
    }

    public function test_pending_reservation_holds_the_dates(): void
    {
        $this->actingAs($this->guest)->post("/cottages/{$this->cottage->id}/book", $this->payload());

        $this->assertFalse(app(AvailabilityService::class)->isAvailable($this->cottage, '2026-12-12', '2026-12-14'));
    }

    public function test_client_cannot_set_status_price_or_reference(): void
    {
        $this->actingAs($this->guest)->post("/cottages/{$this->cottage->id}/book", $this->payload([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_price' => '1.00',
            'amount_paid' => '99999.00',
            'reference_code' => 'HACKED',
            'user_id' => User::factory()->create()->id,
        ]));

        $booking = Booking::sole();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame('7501.50', $booking->total_price);
        $this->assertSame('0.00', $booking->amount_paid);
        $this->assertNotSame('HACKED', $booking->reference_code);
        $this->assertSame($this->guest->id, $booking->user_id);
    }

    public function test_party_size_is_checked_against_capacity(): void
    {
        $this->actingAs($this->guest)
            ->post("/cottages/{$this->cottage->id}/book", $this->payload(['guests_count' => 5]))
            ->assertSessionHasErrors('guests_count');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_stay_rules_are_enforced_on_submit(): void
    {
        $this->actingAs($this->guest)
            ->post("/cottages/{$this->cottage->id}/book", $this->payload(['check_in' => '2026-10-30', 'check_out' => '2026-11-02']))
            ->assertSessionHasErrors('check_in');

        $this->actingAs($this->guest)
            ->post("/cottages/{$this->cottage->id}/book", $this->payload(['check_out' => '2026-12-10']))
            ->assertSessionHasErrors('check_in');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_phone_and_confirmation_are_required(): void
    {
        $this->actingAs($this->guest)
            ->post("/cottages/{$this->cottage->id}/book", $this->payload(['phone' => '12345', 'agree' => null]))
            ->assertSessionHasErrors(['phone', 'agree']);
    }

    public function test_submit_refuses_dates_taken_in_the_meantime(): void
    {
        Booking::factory()->for($this->cottage)->stay('2026-12-12', '2026-12-15')->status(BookingStatus::Confirmed)->create();

        $this->actingAs($this->guest)
            ->from($this->bookUrl())
            ->post("/cottages/{$this->cottage->id}/book", $this->payload())
            ->assertRedirect($this->bookUrl())
            ->assertSessionHas('error');

        $this->assertSame(1, Booking::count());
    }

    public function test_second_guest_cannot_book_the_same_dates(): void
    {
        $this->actingAs($this->guest)->post("/cottages/{$this->cottage->id}/book", $this->payload());

        $this->actingAs(User::factory()->create())
            ->post("/cottages/{$this->cottage->id}/book", $this->payload())
            ->assertSessionHas('error');

        $this->assertSame(1, Booking::count());
    }

    public function test_changeover_booking_is_accepted(): void
    {
        Booking::factory()->for($this->cottage)->stay('2026-12-07', '2026-12-10')->status(BookingStatus::Confirmed)->create();

        $this->actingAs($this->guest)
            ->post("/cottages/{$this->cottage->id}/book", $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Booking::count());
    }

    public function test_reservation_posts_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->guest)->post("/cottages/{$this->cottage->id}/book", $this->payload(['agree' => null]));
        }

        $this->actingAs($this->guest)->post("/cottages/{$this->cottage->id}/book", $this->payload())->assertStatus(429);
    }
}
