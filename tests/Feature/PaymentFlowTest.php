<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Online payment: checkout → signed webhook → booking totals → receipt.
 * Runs on the fake gateway, which uses PayMongo's exact signature scheme.
 */
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bookease.payments.driver' => 'fake', 'bookease.payments.down_payment_percent' => 50]);
        Notification::fake();
        $this->travelTo('2026-11-01 09:00:00');

        $this->guest = User::factory()->create();
        $cottage = Cottage::factory()->create(['price_per_night' => '2500.25']);
        $this->booking = Booking::factory()->for($cottage)->for($this->guest)
            ->stay('2026-12-10', '2026-12-13')
            ->create(['total_price' => '7500.75']); // 3 × 2500.25
    }

    private function gateway(): FakeGateway
    {
        return app(PaymentGateway::class);
    }

    private function startCheckout(string $kind = 'down', string $method = 'gcash'): Payment
    {
        $this->actingAs($this->guest)
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => $kind, 'method' => $method])
            ->assertRedirectContains('/sandbox/checkout/cs_fake_');

        return Payment::latest('id')->first();
    }

    /**
     * @param  array{body: string, signature: string}  $webhook
     */
    private function sendWebhook(array $webhook): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/webhooks/payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $webhook['signature'],
        ], $webhook['body']);
    }

    // ---------------------------------------------------------------
    // Checkout
    // ---------------------------------------------------------------

    public function test_checkout_creates_a_pending_down_payment_and_changes_nothing_else(): void
    {
        $payment = $this->startCheckout();

        $this->assertSame(PaymentRecordStatus::Pending, $payment->status);
        $this->assertSame(PaymentKind::Down, $payment->kind);
        $this->assertSame(PaymentMethod::GCash, $payment->method);
        $this->assertSame('3750.38', $payment->amount);            // 50% of 7500.75, rounded half-up
        $this->assertStringStartsWith('cs_fake_', $payment->provider_payment_id);
        $this->assertMatchesRegularExpression('/^OR-20261101-[2-9A-Z]{8}$/', $payment->reference_no);

        $this->booking->refresh();
        $this->assertSame('0.00', $this->booking->amount_paid);
        $this->assertSame(PaymentStatus::Unpaid, $this->booking->payment_status);
        Notification::assertNothingSent();
    }

    public function test_returning_from_checkout_never_marks_paid(): void
    {
        $payment = $this->startCheckout();

        $this->actingAs($this->guest)
            ->get("/my/bookings/{$this->booking->reference_code}/payments/{$payment->reference_no}/return")
            ->assertOk()
            ->assertSee('Confirming your payment');

        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
        $this->assertSame('0.00', $this->booking->fresh()->amount_paid);
    }

    public function test_starting_a_new_checkout_expires_the_previous_one(): void
    {
        $first = $this->startCheckout('down', 'gcash');
        $second = $this->startCheckout('full', 'maya');

        $this->assertSame(PaymentRecordStatus::Expired, $first->fresh()->status);
        $this->assertSame(PaymentRecordStatus::Pending, $second->status);
        $this->assertSame('7500.75', $second->amount);
    }

    public function test_only_gcash_and_maya_can_be_chosen_online(): void
    {
        foreach (['cash', 'card', 'bitcoin'] as $method) {
            $this->actingAs($this->guest)
                ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'down', 'method' => $method])
                ->assertSessionHasErrors('method');
        }

        $this->actingAs($this->guest)
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'refund', 'method' => 'gcash'])
            ->assertSessionHasErrors('kind');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guest_cannot_pay_someone_elses_booking(): void
    {
        $this->actingAs(User::factory()->create())
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'down', 'method' => 'gcash'])
            ->assertForbidden();
    }

    public function test_cancelled_booking_cannot_be_paid(): void
    {
        $this->booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        $this->actingAs($this->guest)
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'down', 'method' => 'gcash'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    // ---------------------------------------------------------------
    // Webhook: the only way to "paid"
    // ---------------------------------------------------------------

    public function test_signed_webhook_confirms_payment_updates_booking_and_emails_receipt(): void
    {
        $payment = $this->startCheckout();

        $this->sendWebhook($this->gateway()->makeWebhook($payment))
            ->assertOk()
            ->assertJson(['outcome' => 'paid']);

        $payment->refresh();
        $this->assertSame(PaymentRecordStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->provider_payload);

        $this->booking->refresh();
        $this->assertSame('3750.38', $this->booking->amount_paid);
        $this->assertSame('3750.37', $this->booking->balance());
        $this->assertSame(PaymentStatus::PartiallyPaid, $this->booking->payment_status);

        Notification::assertSentTo($this->guest, PaymentReceived::class);
    }

    public function test_duplicate_webhook_does_not_credit_twice(): void
    {
        $payment = $this->startCheckout();
        $webhook = $this->gateway()->makeWebhook($payment);

        $this->sendWebhook($webhook)->assertJson(['outcome' => 'paid']);
        $this->sendWebhook($webhook)->assertOk()->assertJson(['outcome' => 'duplicate']);
        $this->sendWebhook($this->gateway()->makeWebhook($payment))->assertJson(['outcome' => 'duplicate']);

        $this->assertSame('3750.38', $this->booking->fresh()->amount_paid);
        Notification::assertSentToTimes($this->guest, PaymentReceived::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payment = $this->startCheckout();
        $webhook = $this->gateway()->makeWebhook($payment);
        $webhook['signature'] = preg_replace('/te=[0-9a-f]+/', 'te='.str_repeat('a', 64), $webhook['signature']);

        $this->sendWebhook($webhook)->assertStatus(400);

        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
    }

    public function test_tampered_body_is_rejected(): void
    {
        $payment = $this->startCheckout();
        $webhook = $this->gateway()->makeWebhook($payment);
        $webhook['body'] = str_replace('"amount":375038', '"amount":1', $webhook['body']);

        $this->sendWebhook($webhook)->assertStatus(400);

        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $payment = $this->startCheckout();
        $webhook = $this->gateway()->makeWebhook($payment);
        $webhook['signature'] = '';

        $this->sendWebhook($webhook)->assertStatus(400);
    }

    public function test_replayed_old_webhook_is_rejected(): void
    {
        $payment = $this->startCheckout();
        $old = $this->gateway()->makeWebhook($payment, timestamp: time() - 3600);

        $this->sendWebhook($old)->assertStatus(400);

        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
    }

    public function test_live_mode_events_are_refused_in_sandbox(): void
    {
        $payment = $this->startCheckout();
        $webhook = $this->gateway()->makeWebhook($payment);
        $webhook['body'] = str_replace('"livemode":false', '"livemode":true', $webhook['body']);

        $this->sendWebhook($webhook)->assertStatus(400);
    }

    public function test_correctly_signed_but_wrong_amount_is_not_credited(): void
    {
        $payment = $this->startCheckout();

        $this->sendWebhook($this->gateway()->makeWebhook($payment, amountCentavos: 100))
            ->assertOk()
            ->assertJson(['outcome' => 'mismatch']);

        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
        $this->assertSame('0.00', $this->booking->fresh()->amount_paid);
    }

    public function test_failed_payment_leaves_booking_unpaid(): void
    {
        $payment = $this->startCheckout();

        $this->sendWebhook($this->gateway()->makeWebhook($payment, paid: false))->assertJson(['outcome' => 'failed']);

        $this->assertSame(PaymentRecordStatus::Failed, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $this->booking->fresh()->payment_status);
    }

    public function test_unknown_checkout_is_acknowledged_but_ignored(): void
    {
        $payment = $this->startCheckout();
        $payment->forceFill(['provider_payment_id' => 'cs_fake_other'])->save();
        $ghost = (clone $payment)->forceFill(['provider_payment_id' => 'cs_fake_does_not_exist']);

        $this->sendWebhook($this->gateway()->makeWebhook($ghost))->assertOk()->assertJson(['outcome' => 'unknown']);
        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
    }

    public function test_paid_webhook_still_wins_after_guest_backed_out(): void
    {
        $payment = $this->startCheckout();
        $this->actingAs($this->guest)
            ->get("/my/bookings/{$this->booking->reference_code}/payments/{$payment->reference_no}/return?cancelled=1");
        $this->assertSame(PaymentRecordStatus::Expired, $payment->fresh()->status);

        $this->sendWebhook($this->gateway()->makeWebhook($payment->fresh()))->assertJson(['outcome' => 'paid']);

        $this->assertSame(PaymentRecordStatus::Paid, $payment->fresh()->status);
    }

    public function test_down_payment_then_balance_makes_booking_fully_paid(): void
    {
        $down = $this->startCheckout('down');
        $this->sendWebhook($this->gateway()->makeWebhook($down));

        $this->actingAs($this->guest)->get("/my/bookings/{$this->booking->reference_code}")
            ->assertSee('Remaining balance')->assertSee('₱3,750.37');

        $balance = $this->startCheckout('balance', 'maya');
        $this->assertSame('3750.37', $balance->amount);
        $this->sendWebhook($this->gateway()->makeWebhook($balance));

        $this->booking->refresh();
        $this->assertSame('7500.75', $this->booking->amount_paid);
        $this->assertSame('0.00', $this->booking->balance());
        $this->assertSame(PaymentStatus::Paid, $this->booking->payment_status);

        // Nothing left to pay → no payment form, further attempts refused.
        $this->actingAs($this->guest)->get("/my/bookings/{$this->booking->reference_code}")->assertDontSee('Pay securely online');
        $this->actingAs($this->guest)
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => 'balance', 'method' => 'gcash'])
            ->assertSessionHas('error');
    }

    public function test_status_endpoint_reports_paid_with_receipt_link(): void
    {
        $payment = $this->startCheckout();
        $url = "/my/bookings/{$this->booking->reference_code}/payments/{$payment->reference_no}/status";

        $this->actingAs($this->guest)->getJson($url)->assertJson(['status' => 'pending', 'receipt_url' => null]);
        $this->sendWebhook($this->gateway()->makeWebhook($payment));
        $this->actingAs($this->guest)->getJson($url)->assertJson(['status' => 'paid'])->assertJsonPath('receipt_url', route('receipts.show', $payment));
    }

    // ---------------------------------------------------------------
    // Sandbox checkout page
    // ---------------------------------------------------------------

    public function test_sandbox_checkout_page_pays_through_the_signed_webhook_path(): void
    {
        $payment = $this->startCheckout();
        $success = route('guest.payments.return', [$this->booking, $payment]);

        $this->get("/sandbox/checkout/{$payment->provider_payment_id}?success=".urlencode($success))
            ->assertOk()
            ->assertSee('SANDBOX');

        $this->post("/sandbox/checkout/{$payment->provider_payment_id}", ['outcome' => 'pay', 'success' => $success])
            ->assertRedirect($success);

        $this->assertSame(PaymentRecordStatus::Paid, $payment->fresh()->status);
    }

    public function test_sandbox_checkout_will_not_redirect_off_site(): void
    {
        $payment = $this->startCheckout();

        $this->post("/sandbox/checkout/{$payment->provider_payment_id}", ['outcome' => 'fail', 'cancel' => 'https://evil.example/phish'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_sandbox_checkout_is_disabled_for_the_real_gateway(): void
    {
        $payment = $this->startCheckout();
        config(['bookease.payments.driver' => 'paymongo', 'services.paymongo.webhook_secret' => 'whsk_test']);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->get("/sandbox/checkout/{$payment->provider_payment_id}")->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Receipts
    // ---------------------------------------------------------------

    public function test_receipt_is_visible_to_owner_and_staff_only(): void
    {
        $payment = $this->startCheckout();
        $this->sendWebhook($this->gateway()->makeWebhook($payment));
        $url = "/receipts/{$payment->reference_no}";

        $this->actingAs($this->guest)->get($url)
            ->assertOk()
            ->assertSee($payment->reference_no)
            ->assertSee('₱3,750.38')
            ->assertSee('Payment provider (signed callback)');

        $this->actingAs(User::factory()->staff()->create())->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->get('/logout');
    }

    public function test_unpaid_payment_has_no_receipt(): void
    {
        $payment = $this->startCheckout();

        $this->actingAs($this->guest)->get("/receipts/{$payment->reference_no}")->assertNotFound();
    }

    public function test_money_helpers_used_by_payments(): void
    {
        $this->assertSame('3750.38', Money::percentOf('7500.75', 50));
        $this->assertSame('2250.23', Money::percentOf('7500.75', 30));
        $this->assertSame(375038, Money::toCentavos('3750.38'));
        $this->assertSame('3750.38', Money::fromCentavos(375038));
    }
}
