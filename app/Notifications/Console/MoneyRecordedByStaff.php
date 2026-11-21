<?php

namespace App\Notifications\Console;

use App\Enums\PaymentMethod;

/** Front-desk money the owner keeps an eye on: cash taken in, GCash/Maya transfers verified, refunds paid out. */
class MoneyRecordedByStaff extends PaymentAlert
{
    public function kind(): string
    {
        return $this->payment->isRefund() ? 'payment.refund' : 'payment.cash';
    }

    public function title(): string
    {
        return match (true) {
            $this->payment->isRefund() => 'Refund recorded',
            $this->payment->method === PaymentMethod::Cash => 'Cash payment recorded',
            default => "{$this->payment->method->label()} transfer verified",
        };
    }

    public function body(): string
    {
        $by = $this->payment->recordedBy?->name ?? 'the front desk';

        return "{$this->amount()} by {$by} · {$this->reference()}";
    }
}
