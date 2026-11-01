<?php

namespace App\Exceptions;

use RuntimeException;

/** Another booking already holds some of the requested nights. */
class DatesUnavailableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Sorry, those dates were just taken. Please choose different dates.');
    }
}
