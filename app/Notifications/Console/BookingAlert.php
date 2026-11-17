<?php

namespace App\Notifications\Console;

use App\Models\Booking;

/** Base for alerts about one booking; they all open that booking. */
abstract class BookingAlert extends ConsoleNotification
{
    public function __construct(public readonly Booking $booking) {}

    public function reference(): ?string
    {
        return $this->booking->reference_code;
    }

    /** "Ana Reyes · Seaside Cottage A · Nov 24–Nov 26" */
    protected function stay(): string
    {
        $b = $this->booking;

        return "{$b->user->name} · {$b->cottage->name} · {$b->check_in->format('M j')}–{$b->check_out->format('M j')}";
    }
}
