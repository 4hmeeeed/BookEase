<?php

namespace App\Notifications\Console;

use App\Models\Booking;

/** A guest submitted a reservation that is waiting for approval. */
class NewBookingRequest extends ConsoleNotification
{
    public function __construct(public readonly Booking $booking) {}

    public function kind(): string
    {
        return 'booking.requested';
    }

    public function title(): string
    {
        return 'New booking request';
    }

    public function body(): string
    {
        $b = $this->booking;

        return "{$b->user->name} · {$b->cottage->name} · {$b->check_in->format('M j')}–{$b->check_out->format('M j')}";
    }

    public function reference(): ?string
    {
        return $this->booking->reference_code;
    }
}
