<?php

namespace App\Notifications\Console;

use App\Models\Payment;
use App\Support\Money;

/** Base for alerts about one payment; they all open the payment's booking. */
abstract class PaymentAlert extends ConsoleNotification
{
    public function __construct(public readonly Payment $payment) {}

    public function reference(): ?string
    {
        return $this->payment->booking->reference_code;
    }

    public function paymentReference(): ?string
    {
        return $this->payment->reference_no;
    }

    protected function amount(): string
    {
        return Money::format($this->payment->amount);
    }
}
