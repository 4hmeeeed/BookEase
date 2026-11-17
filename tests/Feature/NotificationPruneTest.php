<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Console\ChatbotUnanswered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Read alerts are kept for 90 days, then deleted (Data Privacy Act: keep
 * only what is still needed). Unread ones stay until someone reads them.
 */
class NotificationPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_alerts_older_than_90_days_are_deleted(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $staff = User::factory()->staff()->create();

        $alert = fn (array $attributes): DatabaseNotification => $staff->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ChatbotUnanswered::class,
            'data' => ['kind' => 'chatbot.unanswered', 'title' => 'Questions', 'body' => '1 question today', 'reference' => null, 'count' => 1],
            ...$attributes,
        ]);

        $old = $alert(['read_at' => now()->subDays(91)]);
        $recent = $alert(['read_at' => now()->subDays(89)]);
        $unread = $alert([]);
        $unread->forceFill(['created_at' => now()->subDays(200)])->save();

        $this->artisan('notifications:prune-read')
            ->expectsOutputToContain('Deleted 1 read')
            ->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($unread);
    }

    public function test_the_cleanup_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('notifications:prune-read')
            ->assertSuccessful();
    }
}
