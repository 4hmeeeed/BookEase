<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Cottage $cottage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        $this->cottage = Cottage::factory()->create(['name' => 'Seaside Cottage', 'price_per_night' => '2500.00', 'capacity' => 4]);

        Booking::factory()->for($this->cottage)->stay('2026-12-10', '2026-12-13')->status(BookingStatus::Confirmed)->create();
    }

    // ---------------- /cottages with dates ----------------

    public function test_date_search_hides_units_booked_for_those_dates(): void
    {
        Cottage::factory()->create(['name' => 'Garden Room', 'price_per_night' => '1800.00']);

        $this->get('/cottages?check_in=2026-12-11&check_out=2026-12-12')
            ->assertOk()
            ->assertSee('Garden Room')
            ->assertDontSee('Seaside Cottage')
            ->assertSee('1 unit available');
    }

    public function test_date_search_allows_changeover_and_shows_server_total(): void
    {
        $this->get('/cottages?check_in=2026-12-13&check_out=2026-12-16')
            ->assertOk()
            ->assertSee('Seaside Cottage')
            ->assertSee('₱7,500.00')       // 3 nights × ₱2,500.00
            ->assertSee('3 nights');
    }

    public function test_date_search_combines_with_party_size(): void
    {
        $this->get('/cottages?check_in=2026-12-20&check_out=2026-12-21&guests=6')
            ->assertOk()
            ->assertDontSee('Seaside Cottage')
            ->assertSee('already booked', false);
    }

    public function test_invalid_dates_show_a_message_instead_of_redirecting(): void
    {
        $this->get('/cottages?check_in=2026-12-12&check_out=2026-12-10')
            ->assertOk()
            ->assertSee('Check-out must be at least one day after check-in');

        $this->get('/cottages?check_in=2026-10-01&check_out=2026-10-03')
            ->assertOk()
            ->assertSee('in the past');
    }

    // ---------------- availability JSON ----------------

    public function test_month_endpoint_lists_booked_nights_only(): void
    {
        $this->getJson("/cottages/{$this->cottage->id}/availability?month=2026-12")
            ->assertOk()
            ->assertExactJson([
                'month' => '2026-12',
                'booked' => ['2026-12-10', '2026-12-11', '2026-12-12'],
            ]);
    }

    public function test_month_endpoint_never_leaks_guest_details(): void
    {
        $json = $this->getJson("/cottages/{$this->cottage->id}/availability?month=2026-12")->getContent();

        $this->assertStringNotContainsString('user', $json);
        $this->assertStringNotContainsString('reference', $json);
        $this->assertStringNotContainsString('@', $json);
    }

    public function test_month_endpoint_defaults_to_current_month_for_junk_input(): void
    {
        $this->getJson("/cottages/{$this->cottage->id}/availability?month=2026-13")
            ->assertOk()
            ->assertJsonPath('month', '2026-11');
    }

    // ---------------- quote JSON ----------------

    public function test_quote_for_free_dates(): void
    {
        $this->getJson("/cottages/{$this->cottage->id}/quote?check_in=2026-12-13&check_out=2026-12-15")
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'available' => true,
                'nights' => 2,
                'total' => '5000.00',
                'total_formatted' => '₱5,000.00',
            ]);
    }

    public function test_quote_flags_taken_dates(): void
    {
        $this->getJson("/cottages/{$this->cottage->id}/quote?check_in=2026-12-12&check_out=2026-12-14")
            ->assertOk()
            ->assertJson(['ok' => true, 'available' => false]);
    }

    public function test_quote_rejects_invalid_stay(): void
    {
        $this->getJson("/cottages/{$this->cottage->id}/quote?check_in=2026-12-12")
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_endpoints_404_for_delisted_units(): void
    {
        $this->cottage->update(['is_active' => false]);

        $this->getJson("/cottages/{$this->cottage->id}/availability")->assertNotFound();
        $this->getJson("/cottages/{$this->cottage->id}/quote?check_in=2026-12-20&check_out=2026-12-21")->assertNotFound();
    }

    public function test_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson("/cottages/{$this->cottage->id}/availability");
        }

        $this->getJson("/cottages/{$this->cottage->id}/availability")->assertStatus(429);
    }

    public function test_detail_page_renders_calendar_with_prefilled_dates(): void
    {
        $this->get("/cottages/{$this->cottage->id}?check_in=2026-12-13&check_out=2026-12-15")
            ->assertOk()
            ->assertSee('Pick your dates')
            ->assertSee('2026-12-13', false)
            ->assertSee('x-data="stayCalendar(', false)
            ->assertSee('quoteUrl', false);
    }
}
