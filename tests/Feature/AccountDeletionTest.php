<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a user cascades to their bookings, payments, and booking history,
 * so self-service deletion must never erase records the resort has to keep.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_delete_their_own_account(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($staff->fresh());
        $this->assertAuthenticatedAs($staff);
    }

    public function test_owner_cannot_delete_their_own_account(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($owner->fresh());
    }

    public function test_guest_with_a_booking_that_holds_dates_cannot_delete_their_account(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->for($guest)->status(BookingStatus::Confirmed)->create();

        $this->actingAs($guest)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($guest->fresh());
        $this->assertNotNull($booking->fresh());
    }

    public function test_guest_with_settled_payments_cannot_delete_their_account(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->for($guest)->status(BookingStatus::CheckedOut)
            ->stay(now()->subDays(5)->toDateString(), now()->subDays(3)->toDateString())
            ->create();
        $payment = Payment::factory()->for($booking)->paid()->create();

        $this->actingAs($guest)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($guest->fresh());
        $this->assertNotNull($payment->fresh());
    }

    public function test_guest_with_only_released_unpaid_bookings_can_delete_their_account(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->for($guest)->status(BookingStatus::Cancelled)->create();
        // An abandoned checkout moved no money, so it is not a record to keep.
        Payment::factory()->for($booking)->create(['status' => PaymentRecordStatus::Expired]);

        $this->actingAs($guest)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($guest->fresh());
    }
}
