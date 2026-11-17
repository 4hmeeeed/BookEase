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
     * Close a hosted checkout so it can no longer be paid. Hosted checkouts
     * stay open until paid or expired, so a superseded one must be closed.
     *
     * @throws \RuntimeException when the gateway refuses, e.g. a payment on it is already under way
     */
    public function expireCheckout(string $checkoutId): void;

    /**
     * Verify the signature of a raw webhook body and parse it.
     *
     * @throws InvalidWebhookException when the signature, timestamp, or mode is not acceptable
     */
    public function parseWebhook(string $rawBody, ?string $signatureHeader): WebhookEvent;

    /** Short name stored for reconciliation, e.g. "paymongo". */
    public function name(): string;
}
