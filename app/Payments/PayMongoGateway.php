<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayMongo Checkout Sessions (https://docs.paymongo.com).
 *
 *   Create:  POST {base}/checkout_sessions   (HTTP Basic, secret key as username)
 *   Webhook: header  Paymongo-Signature: t=<unix>,te=<test sig>,li=<live sig>
 *            sig = HMAC-SHA256( "<t>.<raw body>", webhook secret )
 *
 * ⚠ Confirm the signed-string format against a real sandbox event before
 *   go-live; PayMongo's dashboard can send a test webhook.
 */
class PayMongoGateway implements PaymentGateway
{
    public function __construct(
        protected readonly ?string $secretKey,
        protected readonly ?string $webhookSecret,
        protected readonly string $baseUrl = 'https://api.paymongo.com/v1',
        protected readonly int $toleranceSeconds = 300,
        protected readonly bool $allowLiveMode = false,
    ) {}

    public function name(): string
    {
        return 'paymongo';
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $this->ensureUsableKey();

        $booking = $payment->booking;

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(15)
                ->post("{$this->baseUrl}/checkout_sessions", [
                    'data' => [
                        'attributes' => [
                            'line_items' => [[
                                'name' => "{$payment->kind->label()} · {$booking->cottage->name}",
                                'description' => "Booking {$booking->reference_code}, ".
                                    $booking->check_in->format('M j').'–'.$booking->check_out->format('M j, Y'),
                                'amount' => Money::toCentavos($payment->amount),
                                'currency' => 'PHP',
                                'quantity' => 1,
                            ]],
                            'payment_method_types' => [$this->methodType($payment->method)],
                            'description' => "Labnasan Resort booking {$booking->reference_code}",
                            'reference_number' => $payment->reference_no,
                            'success_url' => $successUrl,
                            'cancel_url' => $cancelUrl,
                            'send_email_receipt' => false,
                            'metadata' => [
                                'payment_reference' => $payment->reference_no,
                                'booking_reference' => $booking->reference_code,
                            ],
                        ],
                    ],
                ])
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            throw new RuntimeException('The payment provider could not start checkout. Please try again.', previous: $e);
        }

        $id = $response->json('data.id');
        $url = $response->json('data.attributes.checkout_url');

        if (! $id || ! $url) {
            throw new RuntimeException('Unexpected response from the payment provider.');
        }

        return new CheckoutSession($id, $url);
    }

    /**
     * POST {base}/checkout_sessions/{id}/expire. PayMongo answers 400 when the
     * session is already paid, already expired, or has a payment under way.
     */
    public function expireCheckout(string $checkoutId): void
    {
        $this->ensureUsableKey();

        try {
            Http::withBasicAuth($this->secretKey, '')
                ->acceptJson()
                ->timeout(15)
                ->post("{$this->baseUrl}/checkout_sessions/".rawurlencode($checkoutId).'/expire')
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            throw new RuntimeException("The payment provider could not close checkout {$checkoutId}.", previous: $e);
        }
    }

    /** Payments stay in sandbox until the owner explicitly allows live mode. */
    protected function ensureUsableKey(): void
    {
        if (blank($this->secretKey)) {
            throw new RuntimeException('PAYMONGO_SECRET_KEY is not set.');
        }

        if (! $this->allowLiveMode && ! str_starts_with($this->secretKey, 'sk_test_')) {
            throw new RuntimeException('Refusing to use a live PayMongo key while payments are in sandbox mode.');
        }
    }

    public function parseWebhook(string $rawBody, ?string $signatureHeader): WebhookEvent
    {
        $parts = $this->parseSignatureHeader($signatureHeader);
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new InvalidWebhookException('Malformed webhook body.');
        }

        $event = $payload['data']['attributes'] ?? [];
        $livemode = (bool) ($event['livemode'] ?? false);

        if ($livemode && ! $this->allowLiveMode) {
            throw new InvalidWebhookException('Live-mode events are not accepted while in sandbox.');
        }

        $expected = $this->sign($parts['t'].'.'.$rawBody);
        $given = $livemode ? ($parts['li'] ?? '') : ($parts['te'] ?? '');

        if ($given === '' || ! hash_equals($expected, $given)) {
            throw new InvalidWebhookException('Invalid webhook signature.');
        }

        if (abs(time() - (int) $parts['t']) > $this->toleranceSeconds) {
            throw new InvalidWebhookException('Webhook timestamp is outside the allowed window.');
        }

        return $this->toEvent($event, $livemode, $rawBody);
    }

    /**
     * @param  array<string, mixed>  $event  data.attributes of the PayMongo event
     */
    protected function toEvent(array $event, bool $livemode, string $rawBody): WebhookEvent
    {
        $type = (string) ($event['type'] ?? '');
        $resource = $event['data'] ?? [];

        if ($type !== 'checkout_session.payment.paid') {
            // payment.failed etc. carry no checkout id; the pending row simply
            // stays unpaid and the guest can retry. Acknowledge and move on.
            return new WebhookEvent(WebhookEvent::IGNORED, $type, livemode: $livemode, rawBody: $rawBody);
        }

        $attrs = $resource['attributes'] ?? [];
        $paymentAttrs = $attrs['payments'][0]['attributes'] ?? [];

        if (($paymentAttrs['status'] ?? null) !== 'paid') {
            return new WebhookEvent(WebhookEvent::FAILED, $type, $resource['id'] ?? null, livemode: $livemode, rawBody: $rawBody);
        }

        $methodType = $attrs['payment_method_used'] ?? ($paymentAttrs['source']['type'] ?? null);

        return new WebhookEvent(
            outcome: WebhookEvent::PAID,
            type: $type,
            checkoutId: $resource['id'] ?? null,
            amountCentavos: isset($paymentAttrs['amount']) ? (int) $paymentAttrs['amount'] : null,
            currency: $paymentAttrs['currency'] ?? null,
            method: match ($methodType) {
                'gcash' => PaymentMethod::GCash,
                'paymaya', 'maya' => PaymentMethod::Maya,
                default => null,
            },
            livemode: $livemode,
            rawBody: $rawBody,
        );
    }

    /**
     * @return array{t: string, te?: string, li?: string}
     */
    protected function parseSignatureHeader(?string $header): array
    {
        if (blank($this->webhookSecret)) {
            throw new InvalidWebhookException('Webhook secret is not configured.');
        }

        $parts = [];
        foreach (explode(',', (string) $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$k] = $v;
        }

        if (! isset($parts['t']) || ! ctype_digit($parts['t'])) {
            throw new InvalidWebhookException('Missing or malformed signature header.');
        }

        return $parts;
    }

    protected function sign(string $data): string
    {
        return hash_hmac('sha256', $data, (string) $this->webhookSecret);
    }

    protected function methodType(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::GCash => 'gcash',
            PaymentMethod::Maya => 'paymaya',
            default => throw new RuntimeException("{$method->label()} is not an online payment method."),
        };
    }
}
