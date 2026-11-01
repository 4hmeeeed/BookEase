<?php

namespace Tests\Feature;

use App\Enums\CottageType;
use App\Models\Cottage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CottageManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Seaside Cottage 3',
            'type' => CottageType::Cottage->value,
            'capacity' => 6,
            'price_per_night' => '2500.50',
            'description' => 'Beachfront, with a picnic table.',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_only_admins_can_reach_cottage_management(): void
    {
        $cottage = Cottage::factory()->create();

        foreach ([User::factory()->create(), User::factory()->staff()->create()] as $user) {
            $this->actingAs($user)->get('/admin/cottages')->assertForbidden();
            $this->actingAs($user)->post('/admin/cottages', $this->validPayload())->assertForbidden();
            $this->actingAs($user)->put("/admin/cottages/{$cottage->id}", $this->validPayload())->assertForbidden();
            $this->actingAs($user)->delete("/admin/cottages/{$cottage->id}")->assertForbidden();
        }

        $this->assertDatabaseCount('cottages', 1);
        $this->actingAs($this->admin)->get('/admin/cottages')->assertOk()->assertSee($cottage->name);
    }

    public function test_admin_forms_render(): void
    {
        $cottage = Cottage::factory()->create(['price_per_night' => '3200.00']);

        $this->actingAs($this->admin)->get('/admin/cottages/create')->assertOk()->assertSee('Add a cottage');
        $this->actingAs($this->admin)->get("/admin/cottages/{$cottage->id}/edit")
            ->assertOk()
            ->assertSee($cottage->name)
            ->assertSee('value="3200.00"', false);
    }

    public function test_admin_can_create_a_cottage_with_exact_price_and_photo(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cottages', $this->validPayload([
                'price_per_night' => '₱1,999.99',
                'image' => UploadedFile::fake()->image('cottage.jpg', 800, 600),
            ]))
            ->assertRedirect('/admin/cottages')
            ->assertSessionHasNoErrors();

        $cottage = Cottage::sole();

        $this->assertSame('1999.99', $cottage->price_per_night);
        $this->assertSame(CottageType::Cottage, $cottage->type);
        $this->assertTrue($cottage->is_active);
        $this->assertNotNull($cottage->image_path);
        Storage::disk('public')->assertExists($cottage->image_path);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cottages', $this->validPayload([
                'name' => '',
                'type' => 'treehouse',
                'capacity' => 0,
                'price_per_night' => '0.00',
                'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['name', 'type', 'capacity', 'price_per_night', 'image']);

        $this->assertDatabaseCount('cottages', 0);
    }

    public function test_price_with_too_many_decimals_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cottages', $this->validPayload(['price_per_night' => '1500.555']))
            ->assertSessionHasErrors('price_per_night');
    }

    public function test_image_path_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cottages', $this->validPayload(['image_path' => '../../.env']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Cottage::sole()->image_path);
    }

    public function test_admin_can_update_and_replace_photo(): void
    {
        $old = UploadedFile::fake()->image('old.jpg', 800, 600)->store('cottages', 'public');
        $cottage = Cottage::factory()->create(['image_path' => $old]);

        $this->actingAs($this->admin)
            ->put("/admin/cottages/{$cottage->id}", $this->validPayload([
                'name' => 'Renamed Villa',
                'type' => CottageType::Villa->value,
                'price_per_night' => '7000',
                'image' => UploadedFile::fake()->image('new.png', 1200, 900),
            ]))
            ->assertRedirect('/admin/cottages');

        $cottage->refresh();

        $this->assertSame('Renamed Villa', $cottage->name);
        $this->assertSame('7000.00', $cottage->price_per_night);
        $this->assertNotSame($old, $cottage->image_path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($cottage->image_path);
    }

    public function test_admin_can_remove_photo(): void
    {
        $path = UploadedFile::fake()->image('old.jpg', 800, 600)->store('cottages', 'public');
        $cottage = Cottage::factory()->create(['image_path' => $path]);

        $this->actingAs($this->admin)
            ->put("/admin/cottages/{$cottage->id}", $this->validPayload(['remove_image' => '1']));

        $this->assertNull($cottage->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_unchecked_listed_box_delists_the_unit(): void
    {
        $cottage = Cottage::factory()->create();

        $this->actingAs($this->admin)
            ->put("/admin/cottages/{$cottage->id}", $this->validPayload(['is_active' => '0']));

        $this->assertFalse($cottage->fresh()->is_active);
    }

    public function test_admin_can_toggle_listing(): void
    {
        $cottage = Cottage::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/cottages/{$cottage->id}/toggle");
        $this->assertFalse($cottage->fresh()->is_active);

        $this->actingAs($this->admin)->patch("/admin/cottages/{$cottage->id}/toggle");
        $this->assertTrue($cottage->fresh()->is_active);
    }

    public function test_admin_can_delete_a_cottage_and_its_photo(): void
    {
        $path = UploadedFile::fake()->image('c.jpg', 800, 600)->store('cottages', 'public');
        $cottage = Cottage::factory()->create(['image_path' => $path]);

        $this->actingAs($this->admin)
            ->delete("/admin/cottages/{$cottage->id}")
            ->assertRedirect('/admin/cottages');

        $this->assertModelMissing($cottage);
        Storage::disk('public')->assertMissing($path);
    }
}
