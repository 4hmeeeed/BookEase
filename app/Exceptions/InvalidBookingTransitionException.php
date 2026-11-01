<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use RuntimeException;

/** A lifecycle action that isn't allowed from the booking's current state. */
class InvalidBookingTransitionException extends RuntimeException
{
    public static function between(BookingStatus $from, BookingStatus $to): self
    {
        return new self("A {$from->label()} booking can't be changed to {$to->label()}.");
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
