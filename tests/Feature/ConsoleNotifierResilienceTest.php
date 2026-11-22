<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsoleNotifier;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * The notifier never throws: finding recipients and loading what an alert
 * mentions happen inside its guard, so a database error there is logged and
 * the booking or payment that already committed still succeeds for the user.
 */
class ConsoleNotifierResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bookease.payments.down_payment_percent' => 50]);
        $this->travelTo('2026-11-01 09:00:00');
        $this->cottage = Cottage::factory()->create(['capacity' => 4, 'price_per_night' => '2500.00']);
    }

    /** Make every query matching $needle fail, as a dropped connection would. */
    private function failQueriesContaining(string $needle): void
    {
        DB::listen(function (QueryExecuted $query) use ($needle): void {
            if (str_contains($query->sql, $needle)) {
                throw new RuntimeException('Simulated database outage');
            }
        });
    }

    public function test_a_failed_recipient_lookup_does_not_break_a_reservation(): void
    {
        $guest = User::factory()->create();
        User::factory()->staff()->create();
        Log::spy();

        // desk()/owners() is the only query that filters users by role.
        $this->failQueriesContaining('"role" in');

        $this->actingAs($guest)->post("/cottages/{$this->cottage->id}/book", [
            'check_in' => '2026-12-10',
            'check_out' => '2026-12-13',
            'guests_count' => 2,
            'phone' => '+63 917-123-4567',
            'agree' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::where('status', BookingStatus::Pending)->count());
        $this->assertDatabaseCount('notifications', 0);
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'Could not create a console notification')->once();
    }

    public function test_a_failed_recipient_lookup_does_not_break_a_cash_entry(): void
    {
        $staff = User::factory()->staff()->create();
        User::factory()->admin()->create();
        $booking = Booking::factory()->for($this->cottage)->for(User::factory())
            ->stay('2026-12-10', '2026-12-13')
            ->create(['total_price' => '7500.00', 'status' => BookingStatus::Confirmed]);
        Log::spy();

        $this->failQueriesContaining('"role" in');

        $this->actingAs($staff)
            ->post("/staff/bookings/{$booking->reference_code}/cash", ['amount' => '3000'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('3000.00', $booking->fresh()->amount_paid);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_failed_relation_load_is_swallowed_too(): void
    {
        User::factory()->staff()->create();
        $booking = Booking::factory()->for($this->cottage)->for(User::factory())
            ->stay('2026-12-10', '2026-12-13')
            ->create();
        $fresh = Booking::find($booking->id); // no relations loaded yet
        Log::spy();

        $this->failQueriesContaining('from "cottages"');

        app(ConsoleNotifier::class)->bookingRequested($fresh);
        app(ConsoleNotifier::class)->guestCancelled($fresh);

        $this->assertDatabaseCount('notifications', 0);
        Log::shouldHaveReceived('error')->twice();
    }
}
