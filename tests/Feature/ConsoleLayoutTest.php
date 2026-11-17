<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Console\NewBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff and the owner work in the console layout (sidebar on desktop, tab bar
 * on phones); guests keep the resort's top navigation.
 */
class ConsoleLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        $this->staff = User::factory()->staff()->create();
        $this->owner = User::factory()->admin()->create();
    }

    public function test_staff_pages_use_the_console_layout(): void
    {
        $this->actingAs($this->staff)->get('/staff')
            ->assertOk()
            ->assertSee('data-console-sidebar', false)
            ->assertSee('Find a booking');
    }

    public function test_staff_see_only_their_desk_links(): void
    {
        $this->actingAs($this->staff)->get('/staff')
            ->assertOk()
            ->assertDontSee(route('admin.reports.index'))
            ->assertDontSee('Insights');
    }

    public function test_the_owner_sees_every_group_in_order(): void
    {
        $this->actingAs($this->owner)->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Overview', 'Today', 'Front desk', 'Bookings', 'Manage', 'Cottages', 'Staff', 'Insights', 'Reports', 'Chatbot log']);
    }

    public function test_the_bookings_link_shows_the_pending_count(): void
    {
        Booking::factory()->count(2)->create(); // pending by default

        $this->actingAs($this->staff)->get('/staff')->assertSee('data-pending-count="2"', false);
    }

    public function test_the_bell_caps_its_badge_at_nine(): void
    {
        $booking = Booking::factory()->create();
        foreach (range(1, 12) as $i) {
            $this->staff->notify(new NewBookingRequest($booking));
        }

        $this->actingAs($this->staff)->get('/staff')
            ->assertSee('data-unread="12"', false)
            ->assertSee('9+');
    }

    public function test_phones_get_the_owner_tabs(): void
    {
        $this->actingAs($this->owner)->get('/admin')
            ->assertSeeInOrder(['data-console-tabbar', 'Overview', 'Bookings', 'Alerts', 'More'], false);
    }

    public function test_guests_keep_the_top_navigation(): void
    {
        $this->actingAs(User::factory()->create())->get('/my')
            ->assertOk()
            ->assertSee('My stays')
            ->assertDontSee('data-console-sidebar', false);
    }

    public function test_staff_profile_uses_the_console(): void
    {
        $this->actingAs($this->staff)->get('/profile')
            ->assertOk()
            ->assertSee('data-console-sidebar', false);
    }
}
