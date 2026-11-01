<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-receipt email, sent after a payment is confirmed (webhook or front desk). */
class PaymentReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $b = $p->booking;

        return (new MailMessage)
            ->subject("Receipt {$p->reference_no} · Labnasan Resort")
            ->greeting("Salamat, {$notifiable->name}!")
            ->line("We received your {$p->kind->label()} of ".Money::format($p->amount)." via {$p->method->label()} for booking {$b->reference_code} ({$b->cottage->name}).")
            ->line('Remaining balance: '.Money::format($b->balance()).'.')
            ->action('View your e-receipt', route('receipts.show', $p))
            ->line('Keep this receipt for your records.');
    }
}
