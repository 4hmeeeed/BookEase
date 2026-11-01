<?php

namespace Tests\Feature;

use App\Enums\CottageType;
use App\Models\Cottage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CottageBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_see_only_listed_units_ordered_by_rate(): void
    {
        Cottage::factory()->create(['name' => 'Pricey Villa', 'price_per_night' => '6500.00']);
        Cottage::factory()->create(['name' => 'Budget Cabana', 'price_per_night' => '1200.00']);
        Cottage::factory()->delisted()->create(['name' => 'Hidden Room']);

        $this->get('/cottages')
            ->assertOk()
            ->assertSeeInOrder(['Budget Cabana', 'Pricey Villa'])
            ->assertDontSee('Hidden Room')
            ->assertSee('₱1,200.00');
    }

    public function test_filter_by_party_size_uses_capacity(): void
    {
        Cottage::factory()->create(['name' => 'Small Cabana', 'capacity' => 2]);
        Cottage::factory()->create(['name' => 'Exactly Six', 'capacity' => 6]);
        Cottage::factory()->create(['name' => 'Big Villa', 'capacity' => 10]);

        $this->get('/cottages?guests=6')
            ->assertOk()
            ->assertDontSee('Small Cabana')
            ->assertSee('Exactly Six')
            ->assertSee('Big Villa');
    }

    public function test_filter_by_type(): void
    {
        Cottage::factory()->create(['name' => 'Garden Room', 'type' => CottageType::Room]);
        Cottage::factory()->create(['name' => 'Beach Villa', 'type' => CottageType::Villa]);

        $this->get('/cottages?type=villa')
            ->assertSee('Beach Villa')
            ->assertDontSee('Garden Room');
    }

    public function test_junk_filters_are_ignored_not_errors(): void
    {
        Cottage::factory()->create(['name' => 'Any Unit']);

        $this->get('/cottages?guests=-5&type=spaceship')
            ->assertOk()
            ->assertSee('Any Unit');
    }

    public function test_empty_state_when_nothing_fits(): void
    {
        Cottage::factory()->create(['capacity' => 4]);

        $this->get('/cottages?guests=50')->assertOk()->assertSee('No units match');
    }

    public function test_listed_unit_detail_page(): void
    {
        $cottage = Cottage::factory()->create(['name' => 'Seaside Cottage', 'price_per_night' => '2500.00', 'capacity' => 5]);

        $this->get("/cottages/{$cottage->id}")
            ->assertOk()
            ->assertSee('Seaside Cottage')
            ->assertSee('₱2,500.00')
            ->assertSee('5');
    }

    public function test_delisted_unit_detail_page_is_404(): void
    {
        $cottage = Cottage::factory()->delisted()->create();

        $this->get("/cottages/{$cottage->id}")->assertNotFound();
    }
}
