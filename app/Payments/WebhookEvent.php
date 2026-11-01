<?php

namespace App\Payments;

use App\Enums\PaymentMethod;

/**
 * A verified, normalised gateway event.
 */
final readonly class WebhookEvent
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const IGNORED = 'ignored';

    public function __construct(
        public string $outcome,              // self::PAID | self::FAILED | self::IGNORED
        public string $type,                 // raw gateway event type, e.g. checkout_session.payment.paid
        public ?string $checkoutId = null,   // matches payments.provider_payment_id
        public ?int $amountCentavos = null,
        public ?string $currency = null,
        public ?PaymentMethod $method = null,
        public bool $livemode = false,
        public string $rawBody = '',
    ) {}
}
