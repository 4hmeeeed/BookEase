<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use App\Payments\DisabledGateway;
use App\Payments\PaymentGateway;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PAYMENT_DRIVER=none: no online checkout (e.g. hosting that can't receive the
 * gateway's webhooks). Guests pay at the front desk, where staff record cash
 * and issue receipts as usual.
 */
class PayAtResortTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bookease.payments.driver' => 'none', 'bookease.payments.online' => false]);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->travelTo('2026-11-01 09:00:00');

        $this->guest = User::factory()->create();
        $this->booking = Booking::factory()->for(Cottage::factory())->for($this->guest)
            ->stay('2026-12-10', '2026-12-12')
            ->create(['total_price' => '4000.00']);
    }

    public function test_guests_are_offered_no_online_payment(): void
    {
        $this->actingAs($this->guest)->get("/my/bookings/{$this->booking->reference_code}")
            ->assertOk()
            ->assertViewHas('paymentOptions', []);
    }

    public function test_starting_an_online_checkout_is_refused(): void
    {
        $this->actingAs($this->guest)
            ->from("/my/bookings/{$this->booking->reference_code}")
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'down', 'method' => 'gcash'])
            ->assertRedirect("/my/bookings/{$this->booking->reference_code}")
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }

    public function test_the_front_desk_still_takes_cash_and_issues_a_receipt(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->post("/staff/bookings/{$this->booking->reference_code}/cash", ['amount' => '2000'])
            ->assertSessionHas('status');

        $payment = Payment::sole();
        $this->assertSame(PaymentRecordStatus::Paid, $payment->status);
        $this->actingAs($this->guest)->get(route('receipts.show', $payment))->assertOk();
    }

    public function test_the_chatbot_tells_guests_to_pay_at_the_front_desk(): void
    {
        ChatbotService::forgetContext();
        $context = app(ChatbotService::class)->buildContext();

        $this->assertStringContainsString('front desk', $context);
        $this->assertStringNotContainsString('GCash', $context);
    }

    public function test_the_confirmation_email_says_the_balance_is_paid_at_the_resort(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->staff()->create())
            ->post("/staff/bookings/{$this->booking->reference_code}/approve")
            ->assertSessionHas('status');

        $sent = Notification::sent($this->guest, BookingStatusChanged::class)->first();
        $this->assertNotNull($sent);
        $mail = $sent->toMail($this->guest);
        $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

        $this->assertSame(BookingStatus::Confirmed, $sent->status);
        $this->assertStringContainsString('at the resort', $text);
        $this->assertStringNotContainsString('GCash', $text);
    }

    public function test_production_runs_without_an_online_gateway(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->app->forgetInstance(PaymentGateway::class);

        $this->assertInstanceOf(DisabledGateway::class, app(PaymentGateway::class));
    }
}
