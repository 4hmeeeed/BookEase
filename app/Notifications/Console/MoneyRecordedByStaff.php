<?php

namespace App\Notifications\Console;

/** Front-desk money the owner keeps an eye on: cash taken in and refunds paid out. */
class MoneyRecordedByStaff extends PaymentAlert
{
    public function kind(): string
    {
        return $this->payment->isRefund() ? 'payment.refund' : 'payment.cash';
    }

    public function title(): string
    {
        return $this->payment->isRefund() ? 'Refund recorded' : 'Cash payment recorded';
    }

    public function body(): string
    {
        $by = $this->payment->recordedBy?->name ?? 'the front desk';

        return "{$this->amount()} by {$by} · {$this->reference()}";
    }
}
