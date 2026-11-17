<?php

namespace App\Notifications\Console;

/** A guest cancelled their own booking online, so its dates are free again. */
class GuestCancelledBooking extends BookingAlert
{
    public function kind(): string
    {
        return 'booking.cancelled';
    }

    public function title(): string
    {
        return 'Guest cancelled a booking';
    }

    public function body(): string
    {
        return $this->stay().'. The dates are open again.';
    }
}
