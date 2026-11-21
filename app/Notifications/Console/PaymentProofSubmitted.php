<?php

namespace App\Notifications\Console;

use App\Models\PaymentProof;
use App\Support\Money;

/** A guest sent GCash/Maya to the resort's wallet and uploaded the receipt: someone has to check it. */
class PaymentProofSubmitted extends ConsoleNotification
{
    public function __construct(public readonly PaymentProof $proof) {}

    public function kind(): string
    {
        return 'payment.proof';
    }

    public function title(): string
    {
        return "{$this->proof->method->label()} payment to verify";
    }

    public function body(): string
    {
        return Money::format($this->proof->amount).' · '.$this->reference().' · check your '.$this->proof->method->label().' app';
    }

    public function reference(): ?string
    {
        return $this->proof->booking->reference_code;
    }
}
