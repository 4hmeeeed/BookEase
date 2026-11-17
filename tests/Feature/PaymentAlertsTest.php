<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Console\MoneyRecordedByStaff;
use App\Notifications\Console\OnlinePaymentReceived;
use App\Notifications\Console\PaymentNeedsReview;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Money alerts: the desk hears about online payments (and ones that need a
 * second look); the owner hears about cash and refunds recorded by staff.
 */
class PaymentAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private User $staff;

    private User $owner;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bookease.payments.driver' => 'fake', 'bookease.payments.down_payment_percent' => 50]);
        Notification::fake();
        $this->travelTo('2026-11-01 09:00:00');

        $this->guest = User::factory()->create();
        $this->staff = User::factory()->staff()->create();
        $this->owner = User::factory()->admin()->create();
        $cottage = Cottage::factory()->create(['price_per_night' => '2500.25']);
        $this->booking = Booking::factory()->for($cottage)->for($this->guest)
            ->stay('2026-12-10', '2026-12-13')
            ->create(['total_price' => '7500.75']); // 50% down payment = ₱3,750.38
    }

    private function gateway(): FakeGateway
    {
        return app(PaymentGateway::class);
    }

    private function startCheckout(): Payment
    {
        $this->actingAs($this->guest)
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'down', 'method' => 'gcash'])
            ->assertRedirectContains('/sandbox/checkout/cs_fake_');

        return Payment::latest('id')->first();
    }

    /**
     * @param  array{body: string, signature: string}  $webhook
     */
    private function sendWebhook(array $webhook): TestResponse
    {
        return $this->call('POST', '/webhooks/payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $webhook['signature'],
        ], $webhook['body']);
    }

    private function deskUrl(string $action): string
    {
        return "/staff/bookings/{$this->booking->reference_code}/{$action}";
    }

    public function test_a_signed_online_payment_alerts_the_desk_once(): void
    {
        $webhook = $this->gateway()->makeWebhook($this->startCheckout());

        $this->sendWebhook($webhook)->assertJson(['outcome' => 'paid']);
        $this->sendWebhook($webhook)->assertJson(['outcome' => 'duplicate']);

        Notification::assertSentToTimes($this->staff, OnlinePaymentReceived::class, 1);
        Notification::assertSentToTimes($this->owner, OnlinePaymentReceived::class, 1);
        Notification::assertSentTo($this->staff, OnlinePaymentReceived::class, fn (OnlinePaymentReceived $n) => $n->title() === 'GCash payment received'
            && $n->body() === '₱3,750.38 down payment · '.$this->booking->reference_code
            && $n->reference() === $this->booking->reference_code);
        Notification::assertNotSentTo($this->guest, OnlinePaymentReceived::class);
    }

    public function test_a_wrong_amount_payment_asks_the_desk_to_review(): void
    {
        $payment = $this->startCheckout();

        $this->sendWebhook($this->gateway()->makeWebhook($payment, amountCentavos: 100))->assertJson(['outcome' => 'mismatch']);

        Notification::assertSentTo([$this->staff, $this->owner], PaymentNeedsReview::class, fn (PaymentNeedsReview $n) => str_contains($n->body(), $this->booking->reference_code)
            && str_contains($n->body(), '₱3,750.38'));
        Notification::assertNotSentTo([$this->staff, $this->owner], OnlinePaymentReceived::class);
    }

    public function test_a_failed_online_payment_sends_no_alert(): void
    {
        $payment = $this->startCheckout();

        $this->sendWebhook($this->gateway()->makeWebhook($payment, paid: false))->assertJson(['outcome' => 'failed']);

        Notification::assertNotSentTo([$this->staff, $this->owner], OnlinePaymentReceived::class);
        Notification::assertNotSentTo([$this->staff, $this->owner], PaymentNeedsReview::class);
    }

    public function test_cash_from_staff_alerts_the_owner_only(): void
    {
        $otherStaff = User::factory()->staff()->create();

        $this->actingAs($this->staff)->post($this->deskUrl('cash'), ['amount' => '3000'])->assertSessionHas('status');

        Notification::assertSentTo($this->owner, MoneyRecordedByStaff::class, fn (MoneyRecordedByStaff $n) => $n->kind() === 'payment.cash'
            && $n->body() === '₱3,000.00 by '.$this->staff->name.' · '.$this->booking->reference_code);
        Notification::assertNotSentTo([$this->staff, $otherStaff], MoneyRecordedByStaff::class);
    }

    public function test_the_owner_gets_no_alert_for_their_own_refund(): void
    {
        $partner = User::factory()->admin()->create();

        $this->actingAs($this->owner)->post($this->deskUrl('cash'), ['amount' => '3000']);
        $this->actingAs($this->owner)->post($this->deskUrl('refund'), ['refund_amount' => '1000', 'refund_method' => 'cash'])->assertSessionHas('status');

        Notification::assertNotSentTo($this->owner, MoneyRecordedByStaff::class);
        Notification::assertSentTo($partner, MoneyRecordedByStaff::class, fn (MoneyRecordedByStaff $n) => $n->kind() === 'payment.refund'
            && $n->body() === '₱1,000.00 by '.$this->owner->name.' · '.$this->booking->reference_code);
    }

    public function test_an_alert_failure_never_undoes_a_credited_payment(): void
    {
        $payment = $this->startCheckout();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('The notifications table is missing.'));

        $this->sendWebhook($this->gateway()->makeWebhook($payment))->assertJson(['outcome' => 'paid']);

        $this->assertSame('3750.38', $this->booking->fresh()->amount_paid);
    }
}
