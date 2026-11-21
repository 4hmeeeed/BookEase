<?php

namespace App\Notifications;

use App\Models\PaymentProof;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the guest the resort could not match their GCash/Maya transfer, and why. */
class PaymentProofRejected extends Notification
{
    use Queueable;

    public function __construct(public readonly PaymentProof $proof) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->proof;
        $b = $p->booking;

        return (new MailMessage)
            ->subject("We couldn’t confirm your {$p->method->label()} payment · {$b->reference_code}")
            ->greeting("Hello, {$notifiable->name}")
            ->line("We checked your {$p->method->label()} payment of ".Money::format($p->amount)." (reference {$p->reference_no}) for booking {$b->reference_code}, but couldn’t match it to a transfer we received.")
            ->line('Reason: '.$p->review_note)
            ->action('Open your booking', route('guest.bookings.show', $b))
            ->line('You can send a corrected receipt from your booking page, or pay at the front desk.');
    }
}
