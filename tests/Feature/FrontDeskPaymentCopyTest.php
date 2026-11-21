<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * With online payment off (PAYMENT_DRIVER=none), the pages tell guests to pay in
 * person at the resort's front desk instead of online with GCash or Maya.
 * PayAtResortTest covers the payment rules themselves.
 */
class FrontDeskPaymentCopyTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->online(false);
        $this->travelTo('2026-11-01 09:00:00');
        $this->guest = User::factory()->create(['name' => 'Ana Reyes']);
    }

    private function online(bool $on): void
    {
        config(['bookease.payments.driver' => $on ? 'fake' : 'none', 'bookease.payments.online' => $on]);
        $this->app->forgetInstance(PaymentGateway::class);
    }

    /** A ₱4,000 stay for the guest; $paid is recorded straight on the booking. */
    private function booking(BookingStatus $status = BookingStatus::Pending, string $paid = '0.00'): Booking
    {
        $booking = Booking::factory()
            ->for(Cottage::factory()->create(['name' => 'Garden Room']))
            ->for($this->guest)
            ->status($status)
            ->stay('2026-12-10', '2026-12-12')
            ->create(['total_price' => '4000.00']);

        $booking->forceFill(['amount_paid' => $paid])->save();

        return $booking;
    }

    public function test_public_pages_point_to_the_front_desk(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('GCash')
            ->assertSee('Pay at the front desk')
            ->assertSee('How do I pay?')
            ->assertDontSee('How do I pay the down payment?');

        $this->get('/login')->assertOk()->assertDontSee('GCash');
        // Kept short: the sign-up page has to fit one laptop screen.
        $this->get('/register')->assertOk()->assertDontSee('GCash')->assertSee('Book a cottage online and pay at the front desk.');
    }

    public function test_the_guest_dashboard_points_to_the_front_desk(): void
    {
        $this->actingAs($this->guest)->get('/my')->assertOk()
            ->assertDontSee('GCash')
            ->assertSee('Pay in person at the resort')
            ->assertSee('How do I pay?');
    }

    public function test_a_booking_shows_its_balance_payable_at_the_front_desk(): void
    {
        $booking = $this->booking(paid: '1500.00');

        $this->actingAs($this->guest)->get("/my/bookings/{$booking->reference_code}")->assertOk()
            ->assertSee('Payment is made in person')
            ->assertSeeInOrder(['Pay at the front desk', '₱2,500.00', 'is payable at the resort'])
            ->assertDontSee('GCash')
            ->assertDontSee('secure checkout')
            ->assertDontSee('Sandbox mode');
    }

    public function test_no_front_desk_box_when_nothing_is_owed_or_the_booking_is_closed(): void
    {
        $paid = $this->booking(BookingStatus::Confirmed, paid: '4000.00');
        $cancelled = $this->booking(BookingStatus::Cancelled);

        foreach ([$paid, $cancelled] as $booking) {
            $this->actingAs($this->guest)->get("/my/bookings/{$booking->reference_code}")->assertOk()
                ->assertDontSee('Pay at the front desk');
        }
    }

    public function test_staff_refunds_default_to_cash(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Desk Staff']);
        $booking = $this->booking(paid: '2000.00');

        $this->actingAs($staff)->get(route('staff.bookings.show', $booking))->assertOk()
            ->assertSeeInOrder(['name="refund_method"', 'value="cash"', 'value="gcash"', 'value="maya"'], false);
    }

    public function test_with_online_payment_on_the_wallet_wording_stays(): void
    {
        $this->online(true);
        $booking = $this->booking();
        $staff = User::factory()->staff()->create(['name' => 'Desk Staff']);

        $this->get('/')->assertSee('GCash')->assertSee('How do I pay the down payment?');

        $this->actingAs($this->guest)->get("/my/bookings/{$booking->reference_code}")
            ->assertSee('Continue to secure checkout')
            ->assertDontSee('Pay at the front desk');

        $this->actingAs($staff)->get(route('staff.bookings.show', $booking))
            ->assertSeeInOrder(['name="refund_method"', 'value="gcash"', 'value="maya"', 'value="cash"'], false);
    }
}
