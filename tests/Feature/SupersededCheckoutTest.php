<?php

namespace Tests\Feature;

use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Hosted checkouts never close on their own, so when a guest starts a new
 * checkout the old one must be closed at the gateway. Otherwise paying both
 * open checkout pages charges the guest twice.
 */
class SupersededCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-11-01 09:00:00');

        $this->guest = User::factory()->create();
        $this->booking = Booking::factory()->for(Cottage::factory())->for($this->guest)
            ->stay('2026-12-10', '2026-12-13')
            ->create(['total_price' => '6000.00']);
    }

    private function usePayMongo(int $expireStatus = 200): void
    {
        config([
            'bookease.payments.driver' => 'paymongo',
            'services.paymongo.secret_key' => 'sk_test_abc',
            'services.paymongo.webhook_secret' => 'whsk_test',
            'services.paymongo.base_url' => 'https://api.paymongo.com/v1',
        ]);
        $this->app->forgetInstance(PaymentGateway::class);

        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/*/expire' => Http::response(
                $expireStatus === 200
                    ? ['data' => ['id' => 'cs_first', 'type' => 'checkout_session', 'attributes' => ['status' => 'expired']]]
                    : ['errors' => [['code' => 'not_allowed', 'detail' => 'Checkout session has an ongoing payment.']]],
                $expireStatus,
            ),
            'api.paymongo.com/v1/checkout_sessions' => Http::sequence()
                ->push(['data' => ['id' => 'cs_first', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_first']]])
                ->push(['data' => ['id' => 'cs_second', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_second']]]),
        ]);
    }

    private function pay(string $kind = 'down', string $method = 'gcash'): TestResponse
    {
        return $this->actingAs($this->guest)
            ->post("/my/bookings/{$this->booking->reference_code}/pay", ['kind' => $kind, 'method' => $method]);
    }

    public function test_a_new_checkout_closes_the_previous_one_at_the_gateway(): void
    {
        $this->usePayMongo();

        $this->pay('down')->assertRedirect('https://checkout.paymongo.com/cs_first');
        $this->pay('full', 'maya')->assertRedirect('https://checkout.paymongo.com/cs_second');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_first/expire'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_abc:')));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'cs_second/expire'));
    }

    public function test_the_new_checkout_still_starts_when_the_gateway_cannot_close_the_old_one(): void
    {
        $this->usePayMongo(expireStatus: 400);

        $this->pay('down');
        $this->pay('full', 'maya')->assertRedirect('https://checkout.paymongo.com/cs_second');

        $this->assertSame(PaymentRecordStatus::Pending, Payment::where('provider_payment_id', 'cs_second')->sole()->status);
    }

    public function test_an_unreachable_gateway_sends_the_guest_back_with_a_message(): void
    {
        $this->usePayMongo();
        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $this->from("/my/bookings/{$this->booking->reference_code}")
            ->pay()
            ->assertRedirect("/my/bookings/{$this->booking->reference_code}")
            ->assertSessionHas('error');

        $this->assertSame(PaymentRecordStatus::Failed, Payment::sole()->status);
    }

    public function test_an_old_sandbox_checkout_cannot_be_paid_after_a_newer_one_started(): void
    {
        config(['bookease.payments.driver' => 'fake']);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->pay('down');
        $first = Payment::latest('id')->first();
        $this->pay('full');

        $this->get("/sandbox/checkout/{$first->provider_payment_id}")->assertGone();
        $this->post("/sandbox/checkout/{$first->provider_payment_id}", ['outcome' => 'pay'])->assertGone();

        $this->assertSame(PaymentRecordStatus::Expired, $first->fresh()->status);
        $this->assertSame('0.00', $this->booking->fresh()->amount_paid);
    }
}
