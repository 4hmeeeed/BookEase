<?php

namespace Tests\Feature;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Payments\InvalidWebhookException;
use App\Payments\PayMongoGateway;
use App\Payments\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PayMongoGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsk_test_secret';

    private function gateway(string $key = 'sk_test_abc'): PayMongoGateway
    {
        return new PayMongoGateway($key, self::SECRET, 'https://api.paymongo.com/v1', 300, false);
    }

    private function payment(PaymentMethod $method = PaymentMethod::Maya): Payment
    {
        $booking = Booking::factory()
            ->for(Cottage::factory()->create(['name' => 'Seaside Cottage']))
            ->stay('2026-12-10', '2026-12-13')
            ->create();

        return Payment::factory()->for($booking)->create([
            'method' => $method, 'kind' => PaymentKind::Down, 'amount' => '3750.38', 'reference_no' => 'OR-20261101-ABCDEFGH',
        ]);
    }

    public function test_create_checkout_sends_centavos_method_and_reference_with_basic_auth(): void
    {
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => ['id' => 'cs_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_123']],
            ]),
        ]);

        $session = $this->gateway()->createCheckout($this->payment(), 'https://app.test/ok', 'https://app.test/cancel');

        $this->assertSame('cs_123', $session->id);
        $this->assertSame('https://checkout.paymongo.com/cs_123', $session->url);

        Http::assertSent(function (Request $request) {
            $attrs = $request['data']['attributes'];

            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_abc:'))
                && $attrs['line_items'][0]['amount'] === 375038
                && $attrs['line_items'][0]['currency'] === 'PHP'
                && $attrs['payment_method_types'] === ['paymaya']
                && $attrs['reference_number'] === 'OR-20261101-ABCDEFGH'
                && $attrs['success_url'] === 'https://app.test/ok'
                && $attrs['cancel_url'] === 'https://app.test/cancel';
        });
    }

    public function test_gcash_maps_to_gcash_type(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 'cs_1', 'attributes' => ['checkout_url' => 'https://x.test']]])]);

        $this->gateway()->createCheckout($this->payment(PaymentMethod::GCash), 'https://a.test', 'https://b.test');

        Http::assertSent(fn (Request $r) => $r['data']['attributes']['payment_method_types'] === ['gcash']);
    }

    public function test_live_secret_key_is_refused_in_sandbox(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->gateway('sk_live_xyz')->createCheckout($this->payment(), 'https://a.test', 'https://b.test');
    }

    public function test_gateway_errors_become_friendly_exceptions(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['detail' => 'boom']]], 500)]);

        $this->expectExceptionMessage('could not start checkout');
        $this->gateway()->createCheckout($this->payment(), 'https://a.test', 'https://b.test');
    }

    public function test_parses_a_paymongo_signed_paid_event(): void
    {
        $body = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'livemode' => false,
            'data' => ['id' => 'cs_123', 'attributes' => [
                'payment_method_used' => 'paymaya',
                'payments' => [['attributes' => ['amount' => 375038, 'currency' => 'PHP', 'status' => 'paid']]],
            ]],
        ]]]);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);

        $event = $this->gateway()->parseWebhook($body, "t={$t},te={$sig},li=");

        $this->assertSame(WebhookEvent::PAID, $event->outcome);
        $this->assertSame('cs_123', $event->checkoutId);
        $this->assertSame(375038, $event->amountCentavos);
        $this->assertSame(PaymentMethod::Maya, $event->method);
    }

    public function test_live_signature_is_not_accepted_for_a_test_event(): void
    {
        $body = json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => []]]]);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);

        $this->expectException(InvalidWebhookException::class);
        $this->gateway()->parseWebhook($body, "t={$t},te=,li={$sig}");
    }

    public function test_other_event_types_are_ignored(): void
    {
        $body = json_encode(['data' => ['attributes' => ['type' => 'payment.refunded', 'livemode' => false, 'data' => []]]]);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", self::SECRET);

        $this->assertSame(WebhookEvent::IGNORED, $this->gateway()->parseWebhook($body, "t={$t},te={$sig},li=")->outcome);
    }

    public function test_missing_webhook_secret_rejects_everything(): void
    {
        $gateway = new PayMongoGateway('sk_test_abc', null);

        $this->expectException(InvalidWebhookException::class);
        $gateway->parseWebhook('{}', 't=1,te=x,li=');
    }
}
