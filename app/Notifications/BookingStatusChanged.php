<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a guest that the resort confirmed, rejected, or cancelled their
 * reservation, with the reason staff gave.
 */
class BookingStatusChanged extends Notification
{
    use Queueable;

    public readonly BookingStatus $status;

    public function __construct(public readonly Booking $booking, public readonly ?string $remarks = null)
    {
        $this->status = $booking->status;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking;
        $stay = "{$b->cottage->name}, {$this->day($b->check_in)} to {$this->day($b->check_out)}";

        $mail = match ($this->status) {
            BookingStatus::Confirmed => (new MailMessage)
                ->subject("Reservation {$b->reference_code} is confirmed · Labnasan Resort")
                ->greeting("Good news, {$notifiable->name}!")
                ->line("The resort confirmed your reservation {$b->reference_code}: {$stay}.")
                ->line('Check-in is from '.$this->clock(config('bookease.check_in_time')).' and check-out is by '.$this->clock(config('bookease.check_out_time')).'.'),
            BookingStatus::Rejected => (new MailMessage)
                ->subject("Reservation {$b->reference_code} was not approved · Labnasan Resort")
                ->greeting("Hello {$notifiable->name},")
                ->line("We're sorry. The resort couldn't accept your reservation {$b->reference_code}: {$stay}. Those dates are open again."),
            default => (new MailMessage)
                ->subject("Reservation {$b->reference_code} was cancelled · Labnasan Resort")
                ->greeting("Hello {$notifiable->name},")
                ->line("The resort cancelled your reservation {$b->reference_code}: {$stay}."),
        };

        if (filled($this->remarks)) {
            $mail->line(($this->status === BookingStatus::Confirmed ? 'Note from the resort: ' : 'Reason: ').$this->remarks);
        }

        if ($this->status === BookingStatus::Confirmed && Money::isPositive($b->balance())) {
            $mail->line('Balance due: '.Money::format($b->balance()).(config('bookease.payments.online')
                ? '. You can pay it with GCash or Maya from your booking page.'
                : ', payable at the resort\'s front desk.'));
        } elseif ($this->status !== BookingStatus::Confirmed && Money::isPositive($b->amount_paid)) {
            $mail->line('The resort will contact you about the '.Money::format($b->amount_paid).' you already paid.');
        }

        return $mail->action('View your booking', route('guest.bookings.show', $b));
    }

    private function day(CarbonImmutable $date): string
    {
        return $date->format('D, M j, Y');
    }

    /** '14:00' → '2:00 PM', '12:00' → '12:00 NN'. */
    private function clock(?string $time): string
    {
        return $time === '12:00' ? '12:00 NN' : CarbonImmutable::createFromFormat('H:i', (string) $time)->format('g:i A');
    }
}
