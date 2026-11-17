<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemTestEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * /admin/system stands in for SSH on hosting without a command line: it shows
 * whether the server fits the app and runs the few maintenance commands.
 */
class SystemPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->admin()->create(['email' => 'owner@labnasan.test']);
    }

    /** As the owner, with the password just confirmed (the actions need it). */
    private function confirmedOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_only_the_owner_can_open_it(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/system')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/admin/system')->assertForbidden();

        $this->actingAs($this->owner)->get('/admin/system')->assertOk()->assertSee(PHP_VERSION);
    }

    public function test_it_reports_a_missing_requirement(): void
    {
        $this->actingAs($this->owner)->get('/admin/system')
            ->assertOk()
            ->assertViewHas('checks', fn (array $checks) => collect($checks['extensions'])->has('bcmath')
                && $checks['extensions']['bcmath'] === extension_loaded('bcmath'));
    }

    public function test_it_shows_the_visitor_address_seen_through_a_trusted_proxy(): void
    {
        $this->actingAs($this->owner)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/admin/system')
            ->assertViewHas('checks', fn (array $checks) => $checks['visitor_ip'] === '10.0.0.5');

        config(['trustedproxy.proxies' => '*']);

        $this->actingAs($this->owner)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/admin/system')
            ->assertViewHas('checks', fn (array $checks) => $checks['visitor_ip'] === '203.0.113.9');
    }

    public function test_actions_ask_for_the_password_again(): void
    {
        $this->actingAs($this->owner)->post('/admin/system/test-email')->assertRedirect(route('password.confirm'));
        $this->actingAs($this->owner)->post('/admin/system/migrate')->assertRedirect(route('password.confirm'));
    }

    public function test_staff_cannot_run_the_actions(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/system/clear-caches')
            ->assertForbidden();
    }

    public function test_the_owner_can_send_a_test_email(): void
    {
        Notification::fake();

        $this->confirmedOwner()->post('/admin/system/test-email')->assertSessionHas('status');

        Notification::assertSentTo($this->owner, SystemTestEmail::class);
    }

    public function test_a_mail_server_error_is_shown_instead_of_crashing(): void
    {
        Mail::extend('down', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "smtp.gmail.com:587".');
            }

            public function __toString(): string
            {
                return 'down://';
            }
        });
        config(['mail.mailers.down' => ['transport' => 'down'], 'mail.default' => 'down']);

        $this->confirmedOwner()->post('/admin/system/test-email')
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'smtp.gmail.com:587'));
    }

    public function test_the_owner_can_run_database_updates(): void
    {
        $this->confirmedOwner()->post('/admin/system/migrate')->assertSessionHas('status');
    }

    public function test_the_owner_can_clear_the_caches(): void
    {
        // Clearing for real would also wipe the running dev server's caches.
        Artisan::shouldReceive('call')->once()->with('optimize:clear', [])->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('Caches cleared successfully.');

        $this->confirmedOwner()->post('/admin/system/clear-caches')->assertSessionHas('status');
    }

    public function test_the_owner_can_run_the_clean_up_now(): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Console\\NewBookingRequest',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->owner->id,
            'data' => '{}',
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(101),
            'updated_at' => now()->subDays(100),
        ]);

        $this->confirmedOwner()->post('/admin/system/cleanup')->assertSessionHas('status');

        $this->assertSame(0, DB::table('notifications')->count());
    }
}
