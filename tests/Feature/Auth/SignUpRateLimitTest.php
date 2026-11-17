<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each guest account may hold only a few pending requests, so scripted
 * sign-ups must be limited too; reset emails are limited so the form can't
 * be used to flood inboxes.
 */
class SignUpRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_ups_from_one_address_are_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', ['name' => "Bot {$i}", 'email' => 'not-an-email'])->assertRedirect();
        }

        $this->post('/register', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertTooManyRequests();

        $this->assertSame(0, User::count());
    }

    public function test_sign_ups_from_different_addresses_are_counted_separately(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->post('/register', ['name' => "Bot {$i}", 'email' => 'not-an-email']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->post('/register', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_reset_email_requests_from_one_address_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "someone{$i}@example.com"])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'victim@example.com'])->assertTooManyRequests();
    }
}
