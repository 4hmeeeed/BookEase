<?php

namespace App\Notifications\Console;

use Illuminate\Support\Str;

/** The gateway's signed webhook confirmed a GCash/Maya payment. */
class OnlinePaymentReceived extends PaymentAlert
{
    public function kind(): string
    {
        return 'payment.received';
    }

    public function title(): string
    {
        return "{$this->payment->method->label()} payment received";
    }

    public function body(): string
    {
        return $this->amount().' '.Str::lower($this->payment->kind->label()).' · '.$this->reference();
    }
}
