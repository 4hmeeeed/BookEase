<?php

namespace App\Notifications\Console;

/**
 * A signed webhook reported a different amount than the checkout asked for.
 * The payment was not credited, so a person has to look at it.
 */
class PaymentNeedsReview extends PaymentAlert
{
    public function kind(): string
    {
        return 'payment.review';
    }

    public function title(): string
    {
        return 'Payment needs review';
    }

    public function body(): string
    {
        return "A {$this->payment->method->label()} payment for {$this->reference()} arrived with a different amount than the {$this->amount()} requested.";
    }
}
