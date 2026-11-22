<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ChatInquiry;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Console\PaymentNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The owner overview leads with what needs a person, then the month's numbers,
 * then what has happened lately.
 */
class OwnerOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 09:00:00');
        $this->owner = User::factory()->admin()->create();
    }

    private function overview(): TestResponse
    {
        return $this->actingAs($this->owner)->get('/admin')->assertOk();
    }

    public function test_waiting_requests_show_how_long_the_oldest_has_waited(): void
    {
        Booking::factory()->create(['created_at' => now()->subHours(26)]);
        Booking::factory()->create(['created_at' => now()->subHours(2)]);

        $this->overview()->assertSee('2 requests waiting for approval (oldest 26h)');
    }

    public function test_a_request_waiting_two_days_or_more_shows_days(): void
    {
        Booking::factory()->create(['created_at' => now()->subHours(77)]);

        $this->overview()->assertSee('1 request waiting for approval (oldest 3 days)');
    }

    public function test_guests_arriving_soon_with_a_balance_are_listed(): void
    {
        $owing = Booking::factory()->stay('2026-11-12', '2026-11-14')->status(BookingStatus::Confirmed)
            ->create(['total_price' => '3000.00']);
        Booking::factory()->stay('2026-11-11', '2026-11-13')->status(BookingStatus::Confirmed)
            ->create(['total_price' => '2000.00', 'amount_paid' => '2000.00']); // paid in full
        Booking::factory()->stay('2026-11-20', '2026-11-22')->status(BookingStatus::Confirmed)
            ->create(['total_price' => '1500.00']); // more than 3 days away

        $this->overview()
            ->assertSee('1 guest arriving in the next 3 days still owes ₱3,000.00')
            ->assertSee($owing->reference_code);
    }

    public function test_no_shows_are_listed(): void
    {
        $noShow = Booking::factory()->stay('2026-11-07', '2026-11-09')->status(BookingStatus::Confirmed)->create();

        $this->overview()
            ->assertSee('1 confirmed stay ended without a check-in')
            ->assertSee($noShow->reference_code);
    }

    public function test_wrong_amount_payments_are_counted_until_the_alert_is_opened(): void
    {
        $this->owner->notify(new PaymentNeedsReview(Payment::factory()->create()));

        $this->overview()->assertSee('1 online payment arrived with the wrong amount');

        $this->owner->unreadNotifications->markAsRead();

        $this->overview()->assertDontSee('arrived with the wrong amount');
    }

    public function test_wrong_amount_payments_count_payments_not_alerts(): void
    {
        $twice = Payment::factory()->create();
        $this->owner->notify(new PaymentNeedsReview($twice));
        $this->owner->notify(new PaymentNeedsReview($twice)); // a second wrong-amount event for the same checkout
        $this->owner->notify(new PaymentNeedsReview(Payment::factory()->create()));

        $this->overview()->assertSee('2 online payments arrived with the wrong amount');
    }

    public function test_unanswered_chatbot_questions_from_the_last_week_are_counted(): void
    {
        ChatInquiry::factory()->fallback()->count(3)->create();
        ChatInquiry::factory()->fallback()->create(['created_at' => now()->subDays(8)]);
        ChatInquiry::factory()->create();

        $this->overview()->assertSee("3 questions in the last 7 days the assistant couldn't answer");
    }

    public function test_an_overview_with_nothing_to_do_says_so(): void
    {
        $this->overview()->assertSee('Nothing needs you right now');
    }

    public function test_recent_activity_lists_decisions_and_payments_newest_first(): void
    {
        $guest = User::factory()->create(['name' => 'Ana Reyes']);
        $staff = User::factory()->staff()->create(['name' => 'Ben Cruz']);
        $booking = Booking::factory()->for($guest)->for(Cottage::factory()->create(['name' => 'Garden Room']))
            ->stay('2026-11-24', '2026-11-26')->create();

        $this->travelTo('2026-11-10 10:00:00');
        $booking->statusChanges()->create(['user_id' => $guest->id, 'from_status' => null, 'to_status' => BookingStatus::Pending]);
        $this->travelTo('2026-11-10 11:00:00');
        $booking->statusChanges()->create(['user_id' => $staff->id, 'from_status' => BookingStatus::Pending, 'to_status' => BookingStatus::Confirmed]);
        $this->travelTo('2026-11-10 12:00:00');
        Payment::factory()->paid()->for($booking)->create(['amount' => '1000.00']);

        $ref = $booking->reference_code;
        $this->overview()->assertSeeInOrder([
            "GCash ₱1,000.00 received · {$ref}",
            "Ben Cruz approved {$ref}",
            'Ana Reyes requested Garden Room · Nov 24–Nov 26',
        ]);
    }
}
