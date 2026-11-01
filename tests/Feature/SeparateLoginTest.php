<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clients sign in at /login; front-desk staff and the owner at /staff/login.
 * Neither page lets the other group in.
 */
class SeparateLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_pages_render_with_their_own_wording(): void
    {
        $this->get('/login')->assertOk()->assertSee('Guest sign-in')->assertSee('Create an account')
            ->assertDontSee('Front desk &amp; owner', false);

        $this->get('/staff/login')->assertOk()->assertSee('Front desk &amp; owner', false)
            ->assertDontSee('Create an account');
    }

    public function test_client_signs_in_on_the_guest_page(): void
    {
        $guest = User::factory()->create();

        $this->post('/login', ['email' => $guest->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($guest);
    }

    public function test_client_cannot_sign_in_on_the_staff_page(): void
    {
        $guest = User::factory()->create();

        $this->from('/staff/login')
            ->post('/staff/login', ['email' => $guest->email, 'password' => 'password'])
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors(['email' => 'This page is for resort staff. Guests sign in on the main login page.']);

        $this->assertGuest();
    }

    public function test_staff_and_owner_sign_in_on_the_staff_page(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $user) {
            $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/dashboard');

            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect('/staff/login');
            $this->assertGuest();
        }
    }

    public function test_staff_and_owner_cannot_sign_in_on_the_guest_page(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $user) {
            $this->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'Resort staff and the owner sign in on the staff login page.']);

            $this->assertGuest();
        }
    }

    public function test_wrong_password_gives_the_same_generic_error_on_both_pages(): void
    {
        $staff = User::factory()->staff()->create();
        $guest = User::factory()->create();

        // A wrong password must not reveal which page an email belongs to.
        foreach (['/login' => $staff, '/staff/login' => $guest] as $page => $user) {
            $this->from($page)->post($page, ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => trans('auth.failed')]);
        }

        $this->post('/staff/login', ['email' => 'nobody@labnasan.test', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_wrong_page_attempts_count_toward_the_lockout(): void
    {
        $staff = User::factory()->staff()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $staff->email, 'password' => 'password']);
        }

        // Even the right page is locked for this email + IP after 5 tries.
        $this->post('/staff/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_staff_cannot_sign_in(): void
    {
        $staff = User::factory()->staff()->inactive()->create();

        $this->post('/staff/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_signed_out_visitors_are_sent_to_the_right_page(): void
    {
        foreach (['/staff', '/staff/bookings', '/admin', '/admin/reports'] as $uri) {
            $this->get($uri)->assertRedirect('/staff/login');
        }

        foreach (['/my', '/my/bookings', '/dashboard'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_staff_return_to_the_page_they_asked_for(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get('/admin/reports')->assertRedirect('/staff/login');

        $this->post('/staff/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/reports');
    }

    public function test_signed_in_users_are_bounced_from_both_login_pages(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->get('/staff/login')->assertRedirect('/dashboard');
        $this->get('/login')->assertRedirect('/dashboard');
    }

    public function test_public_footer_links_to_the_staff_login(): void
    {
        $this->get('/')->assertSee(route('staff.login'));
    }
}
