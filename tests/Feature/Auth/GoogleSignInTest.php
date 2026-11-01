<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
    }

    private function googleReturns(string $id, string $email, ?string $name = 'Maria Santos', bool $verified = true): void
    {
        $user = (new SocialiteUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
            ->map(['id' => $id, 'email' => $email, 'name' => $name]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    public function test_button_is_hidden_and_routes_are_off_without_credentials(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/login')->assertOk()->assertDontSee('Continue with Google');
        $this->get('/register')->assertOk()->assertDontSee('Sign up with Google');
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_button_shows_on_guest_pages_but_not_on_staff_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertSee(route('google.redirect'));
        $this->get('/register')->assertOk()->assertSee('Sign up with Google');
        $this->get('/staff/login')->assertOk()->assertDontSee('Continue with Google');
    }

    public function test_redirect_sends_the_visitor_to_google(): void
    {
        $response = $this->get('/auth/google');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $location);
        $this->assertStringContainsString('client_id=test-client-id', $location);
        $this->assertStringContainsString(urlencode(url('/auth/google/callback')), $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_a_new_google_account_becomes_a_verified_guest(): void
    {
        $this->googleReturns('g-123', 'Maria@Example.com');

        $response = $this->get('/auth/google/callback?code=abc&state=xyz');

        $response->assertRedirect(route('dashboard', absolute: false));
        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertSame('Maria Santos', $user->name);
        $this->assertSame(UserRole::Guest, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone);
    }

    public function test_a_new_account_without_a_google_name_uses_the_email_name(): void
    {
        $this->googleReturns('g-9', 'juan@example.com', name: null);

        $this->get('/auth/google/callback?code=abc');

        $this->assertSame('juan', User::where('email', 'juan@example.com')->value('name'));
    }

    public function test_an_existing_guest_is_linked_and_signed_in(): void
    {
        $guest = User::factory()->unverified()->create(['email' => 'ana@example.com']);

        $this->googleReturns('g-456', 'ANA@example.com');
        $this->get('/auth/google/callback?code=abc')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($guest);
        $guest->refresh();
        $this->assertSame('g-456', $guest->google_id);
        $this->assertNotNull($guest->email_verified_at);
        $this->assertSame(1, User::count());
    }

    public function test_a_linked_guest_is_found_by_google_id_even_after_changing_email(): void
    {
        $guest = User::factory()->create(['email' => 'new@example.com']);
        $guest->forceFill(['google_id' => 'g-777'])->save();

        $this->googleReturns('g-777', 'old@gmail.com');
        $this->get('/auth/google/callback?code=abc');

        $this->assertAuthenticatedAs($guest);
        $this->assertSame(1, User::count());
    }

    public function test_staff_and_owner_cannot_use_google_sign_in(): void
    {
        foreach (['staff', 'admin'] as $state) {
            $account = User::factory()->{$state}()->create();
            $this->googleReturns('g-'.$state, $account->email);

            $this->get('/auth/google/callback?code=abc')
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['google' => 'Resort staff and the owner sign in on the staff login page.']);

            $this->assertGuest();
            $this->assertNull($account->fresh()->google_id);
        }
    }

    public function test_an_unverified_google_email_is_refused(): void
    {
        $guest = User::factory()->create(['email' => 'victim@example.com']);

        $this->googleReturns('g-attacker', 'victim@example.com', verified: false);

        $this->get('/auth/google/callback?code=abc')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($guest->fresh()->google_id);
    }

    public function test_an_email_linked_to_another_google_account_is_refused(): void
    {
        $guest = User::factory()->create(['email' => 'ana@example.com']);
        $guest->forceFill(['google_id' => 'g-original'])->save();

        $this->googleReturns('g-other', 'ana@example.com');

        $this->get('/auth/google/callback?code=abc')->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame('g-original', $guest->fresh()->google_id);
    }

    public function test_a_deactivated_guest_is_refused(): void
    {
        $guest = User::factory()->inactive()->create();

        $this->googleReturns('g-1', $guest->email);

        $this->get('/auth/google/callback?code=abc')->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_cancelling_on_google_returns_to_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google' => 'Google sign-in was cancelled.']);

        $this->assertGuest();
    }

    public function test_a_forged_or_expired_callback_is_refused(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get('/auth/google/callback?code=abc&state=forged')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_signed_in_users_cannot_reach_the_google_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/auth/google')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_google_id_is_never_mass_assignable(): void
    {
        $user = new User(['name' => 'x', 'email' => 'x@example.com', 'password' => 'secret', 'google_id' => 'g-x']);

        $this->assertNull($user->google_id);
    }
}
