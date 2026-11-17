<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Production starts with one real owner account, made from the command line,
 * instead of the development accounts whose password is "password".
 */
class MakeOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_owner_account(): void
    {
        $this->artisan('bookease:make-owner')
            ->expectsQuestion('Owner name', 'Maria Santos')
            ->expectsQuestion('Owner email', 'Owner@Labnasan.test')
            ->expectsQuestion('Password (at least 12 characters, letters and numbers)', 'Sunset-Cove-2027')
            ->expectsQuestion('Repeat the password', 'Sunset-Cove-2027')
            ->assertSuccessful();

        $owner = User::where('email', 'owner@labnasan.test')->sole();
        $this->assertSame(UserRole::Admin, $owner->role);
        $this->assertTrue($owner->is_active);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(Hash::check('Sunset-Cove-2027', $owner->password));
    }

    public function test_a_weak_password_is_refused(): void
    {
        $this->artisan('bookease:make-owner')
            ->expectsQuestion('Owner name', 'Maria Santos')
            ->expectsQuestion('Owner email', 'owner@labnasan.test')
            ->expectsQuestion('Password (at least 12 characters, letters and numbers)', 'password')
            ->expectsQuestion('Repeat the password', 'password')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_mismatched_passwords_are_refused(): void
    {
        $this->artisan('bookease:make-owner')
            ->expectsQuestion('Owner name', 'Maria Santos')
            ->expectsQuestion('Owner email', 'owner@labnasan.test')
            ->expectsQuestion('Password (at least 12 characters, letters and numbers)', 'Sunset-Cove-2027')
            ->expectsQuestion('Repeat the password', 'Sunset-Cove-2028')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_a_guest_account_is_never_turned_into_the_owner(): void
    {
        $guest = User::factory()->create(['email' => 'owner@labnasan.test']);

        $this->artisan('bookease:make-owner')
            ->expectsQuestion('Owner name', 'Maria Santos')
            ->expectsQuestion('Owner email', 'owner@labnasan.test')
            ->expectsQuestion('Password (at least 12 characters, letters and numbers)', 'Sunset-Cove-2027')
            ->expectsQuestion('Repeat the password', 'Sunset-Cove-2027')
            ->assertFailed();

        $this->assertSame(UserRole::Guest, $guest->fresh()->role);
    }

    public function test_links_use_https_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://labnasanresort.com']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', route('cottages.index'));
    }
}
