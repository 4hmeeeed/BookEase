<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The owner hears when guests ask what the assistant couldn't answer,
 * grouped into one alert a day so a busy evening doesn't flood the bell.
 */
class ChatbotAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-01 09:00:00');
        // Switched off: every question gets the fixed fallback, with no HTTP call.
        config([
            'bookease.chatbot.enabled' => false,
            'bookease.contact' => ['phone' => null, 'email' => null, 'facebook' => null],
        ]);

        $this->owner = User::factory()->admin()->create();
        $this->staff = User::factory()->staff()->create();
    }

    private function ask(): TestResponse
    {
        return $this->postJson('/chatbot', ['question' => 'Do you allow pets?']);
    }

    public function test_an_unanswered_question_alerts_the_owner_only(): void
    {
        $this->ask()->assertJson(['fallback' => true]);

        $this->assertSame('chatbot.unanswered', $this->owner->unreadNotifications()->sole()->data['kind']);
        $this->assertSame(0, $this->staff->notifications()->count());
    }

    public function test_misses_on_the_same_day_share_one_alert(): void
    {
        $this->ask();
        $this->ask();

        $alert = $this->owner->unreadNotifications()->sole();
        $this->assertSame(2, $alert->data['count']);
        $this->assertSame("2 questions today the assistant couldn't answer", $alert->data['body']);
    }

    public function test_a_later_miss_updates_the_alert_so_open_links_keep_working(): void
    {
        $this->ask();
        $link = route('staff.notifications.open', $this->owner->unreadNotifications()->sole()->id);

        // The bell or the Notifications page may still be showing this first version.
        $this->travel(10)->minutes();
        $this->ask();

        $this->actingAs($this->owner)->get($link)
            ->assertRedirect(route('admin.chatbot.index', ['filter' => 'fallback']));

        $alert = $this->owner->notifications()->sole();
        $this->assertSame(2, $alert->data['count']);
        $this->assertSame(now()->toDateTimeString(), $alert->created_at->toDateTimeString()); // back at the top
    }

    public function test_the_bell_notices_when_the_alert_is_updated(): void
    {
        $this->ask();
        $before = $this->actingAs($this->owner)->getJson('/staff/notifications/poll')->json('newest');

        $this->travel(10)->minutes();
        $this->ask();

        // An open dropdown reloads only when this marker changes.
        $this->assertNotSame($before, $this->getJson('/staff/notifications/poll')->json('newest'));
    }

    public function test_a_miss_after_midnight_starts_a_new_alert(): void
    {
        $this->travelTo('2026-11-01 23:50:00');
        $this->ask();
        $this->travelTo('2026-11-02 00:10:00');
        $this->ask();

        $this->assertSame([1, 1], $this->owner->unreadNotifications()->get()->pluck('data.count')->all());
    }

    public function test_a_read_alert_is_not_reused(): void
    {
        $this->ask();
        $this->owner->unreadNotifications->markAsRead();

        $this->ask();

        $this->assertSame(1, $this->owner->unreadNotifications()->sole()->data['count']);
        $this->assertSame(2, $this->owner->notifications()->count());
    }

    public function test_an_answered_question_sends_no_alert(): void
    {
        config([
            'bookease.chatbot.enabled' => true,
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-test-flash',
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'Check-in is at 2:00 PM.']]], 'finishReason' => 'STOP']],
        ])]);

        $this->postJson('/chatbot', ['question' => 'What time is check-in?'])->assertJson(['fallback' => false]);

        $this->assertSame(0, $this->owner->notifications()->count());
    }
}
