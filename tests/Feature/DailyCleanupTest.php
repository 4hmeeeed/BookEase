<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Hosting without cron (InfinityFree) runs the daily clean-up from ordinary
 * page visits instead, at most once a day, so read alerts still go after 90
 * days as the privacy notice promises.
 */
class DailyCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function oldReadNotification(): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\Console\\NewBookingRequest',
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory()->admin()->create()->id,
            'data' => '{}',
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(101),
            'updated_at' => now()->subDays(100),
        ]);

        return $id;
    }

    public function test_a_page_visit_runs_the_clean_up_once_a_day(): void
    {
        config(['bookease.maintenance.on_requests' => true]);
        $first = $this->oldReadNotification();

        $this->get('/')->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $first]);

        // Already ran today: a later visit leaves new candidates alone...
        $second = $this->oldReadNotification();
        $this->get('/')->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $second]);

        // ...until the next day.
        $this->travel(25)->hours();
        $this->get('/')->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $second]);
    }

    public function test_a_failing_clean_up_never_reaches_the_visitor(): void
    {
        config(['bookease.maintenance.on_requests' => true]);
        $this->oldReadNotification();
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower($query->sql), 'delete from "notifications"')) {
                throw new \RuntimeException('Simulated database outage');
            }
        });

        $this->get('/')->assertOk();
    }

    public function test_hosts_with_cron_leave_it_to_the_scheduler(): void
    {
        config(['bookease.maintenance.on_requests' => false]);
        $id = $this->oldReadNotification();

        $this->get('/')->assertOk();

        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }
}
