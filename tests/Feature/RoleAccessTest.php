<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_registrations_are_always_guests(): void
    {
        $this->post('/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin', // attempted privilege escalation
        ]);

        $this->assertSame(UserRole::Guest, User::firstWhere('email', 'juan@example.com')->role);
    }

    public function test_role_cannot_be_changed_through_profile_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
        ]);

        $this->assertSame(UserRole::Guest, $user->fresh()->role);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function dashboardRedirects(): array
    {
        return [
            'guest' => ['guest', '/my'],
            'staff' => ['staff', '/staff'],
            'admin' => ['admin', '/admin'],
        ];
    }

    #[DataProvider('dashboardRedirects')]
    public function test_dashboard_sends_each_role_to_its_own_area(string $role, string $expected): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/dashboard')->assertRedirect($expected);
        $this->actingAs($user)->get($expected)->assertOk();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function accessMatrix(): array
    {
        return [
            'guest → staff area' => ['guest', '/staff', 403],
            'guest → admin area' => ['guest', '/admin', 403],
            'staff → guest area' => ['staff', '/my', 403],
            'staff → admin area' => ['staff', '/admin', 403],
            'staff → staff area' => ['staff', '/staff', 200],
            'admin → staff area' => ['admin', '/staff', 200],
            'admin → admin area' => ['admin', '/admin', 200],
            'admin → guest area' => ['admin', '/my', 403],
        ];
    }

    #[DataProvider('accessMatrix')]
    public function test_role_middleware_enforces_area_separation(string $role, string $uri, int $status): void
    {
        $this->actingAs($this->userWithRole($role))->get($uri)->assertStatus($status);
    }

    public function test_unauthenticated_visitors_are_sent_to_login(): void
    {
        foreach (['/my', '/dashboard'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }

        foreach (['/staff', '/admin'] as $uri) {
            $this->get($uri)->assertRedirect('/staff/login');
        }
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->staff()->inactive()->create();

        $this->post('/staff/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_deactivated_users_are_logged_out_on_next_request(): void
    {
        $user = User::factory()->staff()->create();
        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get('/staff')->assertRedirect('/staff/login');
        $this->assertGuest();
    }

    private function userWithRole(string $role): User
    {
        return match ($role) {
            'admin' => User::factory()->admin()->create(),
            'staff' => User::factory()->staff()->create(),
            default => User::factory()->create(),
        };
    }
}
