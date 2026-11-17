<?php

namespace App\Payments;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local sandbox simulator. Checkout happens on a BookEase page that mimics a
 * hosted wallet page; the outcome is delivered as a PayMongo-shaped event
 * signed with the fake secret and verified by the SAME code as PayMongo.
 * No API keys, no network, no real money. Disabled in production.
 */
class FakeGateway extends PayMongoGateway
{
    private const EXPIRED_KEY = 'fake-checkout-expired:';

    public function name(): string
    {
        return 'fake';
    }

    /** Like PayMongo: an expired checkout page can no longer be paid. */
    public function expireCheckout(string $checkoutId): void
    {
        Cache::put(self::EXPIRED_KEY.$checkoutId, true, now()->addDays(7));
    }

    public function isExpired(string $checkoutId): bool
    {
        return Cache::has(self::EXPIRED_KEY.$checkoutId);
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $id = 'cs_fake_'.Str::lower(Str::random(24));

        return new CheckoutSession($id, route('payments.fake.show', [
            'session' => $id,
            'success' => $successUrl,
            'cancel' => $cancelUrl,
        ]));
    }

    /**
     * Build a signed `checkout_session.payment.paid` (or failed) webhook exactly
     * as PayMongo would send it.
     *
     * @return array{body: string, signature: string}
     */
    public function makeWebhook(Payment $payment, bool $paid = true, ?int $timestamp = null, ?int $amountCentavos = null): array
    {
        $timestamp ??= time();

        $body = json_encode([
            'data' => [
                'id' => 'evt_fake_'.Str::lower(Str::random(16)),
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => $payment->provider_payment_id,
                        'type' => 'checkout_session',
                        'attributes' => [
                            'reference_number' => $payment->reference_no,
                            'payment_method_used' => $payment->method->value === 'maya' ? 'paymaya' : 'gcash',
                            'payments' => [[
                                'id' => 'pay_fake_'.Str::lower(Str::random(16)),
                                'type' => 'payment',
                                'attributes' => [
                                    'amount' => $amountCentavos ?? Money::toCentavos($payment->amount),
                                    'currency' => 'PHP',
                                    'status' => $paid ? 'paid' : 'failed',
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        return [
            'body' => $body,
            'signature' => "t={$timestamp},te={$this->sign($timestamp.'.'.$body)},li=",
        ];
    }
}
