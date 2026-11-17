<?php

namespace App\Payments;

use App\Models\Payment;
use RuntimeException;

/**
 * "Pay at the resort" (PAYMENT_DRIVER=none): no online checkout at all, e.g. on
 * hosting that can't receive the gateway's signed webhooks. Guests pay at the
 * front desk, where staff record cash and issue receipts as usual.
 */
class DisabledGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'none';
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession
    {
        throw new RuntimeException('Online payment is switched off (PAYMENT_DRIVER=none).');
    }

    public function expireCheckout(string $checkoutId): void
    {
        // No checkout is ever opened, so there is nothing to close.
    }

    public function parseWebhook(string $rawBody, ?string $signatureHeader): WebhookEvent
    {
        throw new InvalidWebhookException('Online payment is switched off.');
    }
}
