<?php

namespace Tests\Feature;

use App\Models\Cottage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing page shows only live units and rates from the cottages table.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_without_any_cottages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Cottages are being listed')
            ->assertSee(route('cottages.index'));
    }

    public function test_landing_page_lists_live_cottages_and_the_lowest_rate(): void
    {
        Cottage::factory()->create(['name' => 'Bamboo Hut', 'price_per_night' => '1800.00', 'capacity' => 4]);
        Cottage::factory()->create(['name' => 'Garden Room', 'price_per_night' => '1200.00', 'capacity' => 2]);
        Cottage::factory()->delisted()->create(['name' => 'Old Shed', 'price_per_night' => '500.00']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Bamboo Hut')
            ->assertSee('Garden Room')
            ->assertDontSee('Old Shed')
            ->assertSee('₱1,200.00')
            ->assertDontSee('₱500.00')
            ->assertSeeText('2 units listed');
    }

    public function test_booking_dock_searches_the_cottage_list(): void
    {
        $this->get('/')
            ->assertSee('action="'.route('cottages.index').'"', false)
            ->assertSee('name="check_in"', false)
            ->assertSee('name="check_out"', false)
            ->assertSee('name="guests"', false);
    }
}
