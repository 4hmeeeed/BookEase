<?php

namespace App\Notifications\Console;

/** A guest submitted a reservation that is waiting for approval. */
class NewBookingRequest extends BookingAlert
{
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
        return $this->stay();
    }
}
