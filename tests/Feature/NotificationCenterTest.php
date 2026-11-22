<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Notifications\Console\NewBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/**
 * The notification center behind the bell: each person sees, opens and clears
 * only their own alerts; the bell's background check is cheap and limited.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $otherStaff;

    private User $guest;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        $this->staff = User::factory()->staff()->create();
        $this->otherStaff = User::factory()->staff()->create();
        $this->guest = User::factory()->create();
        $this->booking = Booking::factory()
            ->for(Cottage::factory()->create(['name' => 'Garden Room']))
            ->stay('2026-11-24', '2026-11-26')
            ->create();
    }

    /** Send one new-request alert, then move the clock so every alert has its own time. */
    private function alert(User $to, ?Booking $booking = null): DatabaseNotification
    {
        $to->notify(new NewBookingRequest(($booking ?? $this->booking)->loadMissing(['user', 'cottage'])));
        $alert = $to->notifications()->latest()->first();
        $this->travel(1)->minutes();

        return $alert;
    }

    public function test_guests_cannot_use_the_notification_center(): void
    {
        $this->actingAs($this->guest)->get('/staff/notifications')->assertForbidden();
        $this->actingAs($this->guest)->getJson('/staff/notifications/poll')->assertForbidden();
    }

    public function test_staff_see_only_their_own_alerts(): void
    {
        $villa = Booking::factory()->for(Cottage::factory()->create(['name' => 'Family Villa']))->create();
        $this->alert($this->staff);
        $this->alert($this->otherStaff, $villa);

        $this->actingAs($this->staff)->get('/staff/notifications')
            ->assertOk()
            ->assertSee('Garden Room')
            ->assertDontSee('Family Villa');
    }

    public function test_read_alerts_move_to_the_all_tab(): void
    {
        $alert = $this->alert($this->staff);
        $alert->markAsRead();

        $this->actingAs($this->staff)->get('/staff/notifications')->assertDontSee('Garden Room');
        $this->actingAs($this->staff)->get('/staff/notifications?filter=all')->assertSee('Garden Room');
    }

    public function test_poll_reports_the_unread_count_and_newest_alert(): void
    {
        $this->alert($this->staff);
        $newest = $this->alert($this->staff);
        $this->alert($this->otherStaff);

        $this->actingAs($this->staff)->getJson('/staff/notifications/poll')
            ->assertOk()
            ->assertExactJson(['unread' => 2, 'newest' => $newest->id.'@'.$newest->created_at->getTimestamp()]);
    }

    public function test_the_bell_starts_from_the_newest_alert_on_the_page(): void
    {
        $newest = $this->alert($this->staff);

        // So the first check doesn't ring for an alert that was already showing.
        $this->actingAs($this->staff)->get('/staff')
            ->assertOk()
            ->assertSee("newest: '".$newest->id.'@'.$newest->created_at->getTimestamp()."'", false);
    }

    public function test_opening_an_alert_marks_it_read_and_goes_to_the_booking(): void
    {
        $alert = $this->alert($this->staff);

        $this->actingAs($this->staff)->get("/staff/notifications/{$alert->id}/open")
            ->assertRedirect(route('staff.bookings.show', $this->booking));

        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_opening_someone_elses_alert_is_not_found(): void
    {
        $alert = $this->alert($this->otherStaff);

        $this->actingAs($this->staff)->get("/staff/notifications/{$alert->id}/open")->assertNotFound();

        $this->assertNull($alert->fresh()->read_at);
    }

    public function test_opening_an_alert_for_a_missing_booking_returns_to_the_queue(): void
    {
        $alert = $this->alert($this->staff);
        $this->booking->delete();

        $this->actingAs($this->staff)->get("/staff/notifications/{$alert->id}/open")
            ->assertRedirect(route('staff.bookings.index'))
            ->assertSessionHas('status', 'That booking is no longer available.');
    }

    public function test_mark_all_read_only_touches_your_own(): void
    {
        $this->alert($this->staff);
        $this->alert($this->staff);
        $theirs = $this->alert($this->otherStaff);

        $this->actingAs($this->staff)->post('/staff/notifications/read-all')->assertRedirect();

        $this->assertSame(0, $this->staff->unreadNotifications()->count());
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_the_panel_shows_the_latest_eight_and_poll_counts_all(): void
    {
        foreach (range(1, 12) as $i) {
            $this->alert($this->staff);
        }

        $this->actingAs($this->staff)->getJson('/staff/notifications/poll')->assertJson(['unread' => 12]);

        $panel = $this->actingAs($this->staff)->get('/staff/notifications/panel')->assertOk();
        $this->assertSame(8, substr_count($panel->getContent(), 'data-alert'));
    }

    public function test_an_expired_session_gets_json_401_from_poll(): void
    {
        $this->getJson('/staff/notifications/poll')->assertUnauthorized();
    }

    public function test_an_expired_session_gets_401_from_the_panel_too(): void
    {
        // The dropdown asks for HTML, as the bell does. A redirect would put the sign-in
        // page inside the dropdown and save the bare dropdown as the page to return to.
        $this->get('/staff/notifications/panel', ['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized()
            ->assertSessionMissing('url.intended');
    }

    public function test_poll_is_rate_limited(): void
    {
        $this->actingAs($this->staff);

        foreach (range(1, 30) as $i) {
            $this->getJson('/staff/notifications/poll')->assertOk();
        }

        $this->getJson('/staff/notifications/poll')->assertTooManyRequests();
    }
}
