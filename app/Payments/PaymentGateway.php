<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * A hosted-checkout e-wallet gateway. Guests enter wallet details only on the
 * gateway's page; BookEase receives just the signed outcome.
 */
interface PaymentGateway
{
    /**
     * Start a hosted checkout for a pending Payment row.
     */
    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession;

    /**
     * Verify the signature of a raw webhook body and parse it.
     *
     * @throws InvalidWebhookException when the signature, timestamp, or mode is not acceptable
     */
    public function parseWebhook(string $rawBody, ?string $signatureHeader): WebhookEvent;

    /** Short name stored for reconciliation, e.g. "paymongo". */
    public function name(): string;
}
