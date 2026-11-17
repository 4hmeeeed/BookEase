<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Notifications\Console\NewBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * The front desk and the owner hear about booking activity in the console
 * without having to keep checking the queue.
 */
class BookingAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private User $staff;

    private User $owner;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        $this->guest = User::factory()->create(['name' => 'Ana Reyes']);
        $this->staff = User::factory()->staff()->create();
        $this->owner = User::factory()->admin()->create();
        $this->cottage = Cottage::factory()->create(['name' => 'Seaside Cottage A', 'capacity' => 4]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function book(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->guest)->post("/cottages/{$this->cottage->id}/book", array_merge([
            'check_in' => '2026-11-24',
            'check_out' => '2026-11-26',
            'guests_count' => 2,
            'phone' => '+63 917-123-4567',
            'agree' => '1',
        ], $overrides));
    }

    public function test_a_new_request_alerts_active_staff_and_the_owner(): void
    {
        Notification::fake();
        $inactive = User::factory()->staff()->inactive()->create();

        $this->book()->assertRedirect();

        Notification::assertSentTo([$this->staff, $this->owner], NewBookingRequest::class);
        Notification::assertNotSentTo([$this->guest, $inactive], NewBookingRequest::class);
    }

    public function test_the_alert_stores_only_what_the_console_shows(): void
    {
        $this->book();

        $data = $this->staff->notifications()->sole()->data;

        $this->assertSame(['kind', 'title', 'body', 'reference', 'count'], array_keys($data));
        $this->assertSame('booking.requested', $data['kind']);
        $this->assertSame('New booking request', $data['title']);
        $this->assertSame(Booking::sole()->reference_code, $data['reference']);
        $this->assertSame('Ana Reyes · Seaside Cottage A · Nov 24–Nov 26', $data['body']);
        $this->assertSame(1, $data['count']);
        $this->assertStringNotContainsString($this->guest->email, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function test_a_failed_reservation_sends_no_alert(): void
    {
        Notification::fake();

        $this->book(['guests_count' => 5])->assertSessionHasErrors('guests_count');

        Booking::factory()->for($this->cottage)->stay('2026-11-24', '2026-11-26')->status(BookingStatus::Confirmed)->create();
        $this->book(); // those dates are taken now

        $this->assertSame(1, Booking::count());
        Notification::assertNothingSent();
    }

    public function test_an_alert_failure_never_blocks_a_booking(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('The notifications table is missing.'));

        $this->book()->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::count());
    }
}
