<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, string> */
    private function newStaff(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Santos',
            'email' => 'Maria@Labnasan.test',
            'phone' => '0917 123 4567',
            'password' => 'seashell2026',
            'password_confirmation' => 'seashell2026',
        ], $overrides);
    }

    public function test_only_the_owner_manages_staff(): void
    {
        $staff = User::factory()->staff()->create();

        foreach ([User::factory()->create(), $staff] as $user) {
            $this->actingAs($user)->get('/admin/staff')->assertForbidden();
            $this->actingAs($user)->post('/admin/staff', $this->newStaff())->assertForbidden();
            $this->actingAs($user)->patch("/admin/staff/{$staff->id}/toggle")->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'maria@labnasan.test']);
    }

    public function test_owner_creates_a_staff_account_that_can_sign_in(): void
    {
        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff())
            ->assertRedirect('/admin/staff')
            ->assertSessionHas('status');

        $staff = User::where('email', 'maria@labnasan.test')->sole();
        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertNotNull($staff->email_verified_at);
        $this->assertSame('09171234567', $staff->phone);
        $this->assertTrue(Hash::check('seashell2026', $staff->password));

        auth()->logout();
        $this->post('/login', ['email' => 'maria@labnasan.test', 'password' => 'seashell2026'])->assertRedirect();
        $this->get('/staff')->assertOk();
    }

    public function test_role_cannot_be_smuggled_in(): void
    {
        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff(['role' => 'admin', 'is_active' => '0']));

        $staff = User::where('email', 'maria@labnasan.test')->sole();
        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertTrue($staff->is_active);
    }

    public function test_validation(): void
    {
        User::factory()->create(['email' => 'taken@labnasan.test']);

        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff(['password' => 'short1', 'password_confirmation' => 'short1']))
            ->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff(['password' => 'onlyletterspassword', 'password_confirmation' => 'onlyletterspassword']))
            ->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff(['password_confirmation' => 'different123']))
            ->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff(['email' => 'TAKEN@labnasan.test']))
            ->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/admin/staff', $this->newStaff(['phone' => '12345']))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, User::where('role', UserRole::Staff)->count());
    }

    public function test_editing_details_keeps_the_password_unless_a_new_one_is_given(): void
    {
        $staff = User::factory()->staff()->create(['email' => 'old@labnasan.test']);
        $oldHash = $staff->password;

        $this->actingAs($this->admin)->put("/admin/staff/{$staff->id}", [
            'name' => 'Renamed', 'email' => 'old@labnasan.test', 'phone' => '', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect('/admin/staff')->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame('Renamed', $staff->name);
        $this->assertSame($oldHash, $staff->password);
    }

    public function test_password_reset_signs_the_staff_member_out_everywhere(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->staff()->create();
        $oldToken = $staff->remember_token;
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->put("/admin/staff/{$staff->id}", [
            'name' => $staff->name, 'email' => $staff->email, 'password' => 'newtide2026', 'password_confirmation' => 'newtide2026',
        ])->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertTrue(Hash::check('newtide2026', $staff->password));
        $this->assertNotSame($oldToken, $staff->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
    }

    public function test_deactivating_locks_the_account_and_reactivating_restores_it(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->staff()->create();
        DB::table('sessions')->insert(['id' => 'xyz', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/toggle")->assertSessionHas('status');
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'xyz']);

        // An already-open session is thrown out on the next request.
        $this->actingAs($staff->fresh())->get('/staff')->assertRedirect('/login');
        $this->assertGuest();

        // And a fresh login is refused.
        $this->post('/login', ['email' => $staff->email, 'password' => 'password']);
        $this->assertGuest();

        $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/toggle");
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_guests_and_the_owner_cannot_be_edited_through_the_staff_screen(): void
    {
        $guest = User::factory()->create();

        foreach ([$guest, $this->admin] as $user) {
            $this->actingAs($this->admin)->get("/admin/staff/{$user->id}/edit")->assertNotFound();
            $this->actingAs($this->admin)->patch("/admin/staff/{$user->id}/toggle")->assertNotFound();
            $this->actingAs($this->admin)->put("/admin/staff/{$user->id}", ['name' => 'X', 'email' => $user->email])->assertNotFound();
        }

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertSame(UserRole::Guest, $guest->fresh()->role);
    }

    public function test_staff_list_filters_and_searches(): void
    {
        User::factory()->staff()->create(['name' => 'Active Ana']);
        User::factory()->staff()->inactive()->create(['name' => 'Former Fe']);
        User::factory()->create(['name' => 'Guest Gil']);

        $this->actingAs($this->admin)->get('/admin/staff')
            ->assertOk()->assertSee('Active Ana')->assertSee('Former Fe')->assertDontSee('Guest Gil');

        $this->actingAs($this->admin)->get('/admin/staff?status=inactive')
            ->assertSee('Former Fe')->assertDontSee('Active Ana');

        $this->actingAs($this->admin)->get('/admin/staff?q=ana')
            ->assertSee('Active Ana')->assertDontSee('Former Fe');
    }
}
