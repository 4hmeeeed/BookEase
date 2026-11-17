<?php

namespace App\Support;

use App\Models\Booking;

/** One "Needs attention" row on the owner overview. */
final class AttentionItem
{
    /**
     * @param  string  $tone  attention | money | warning | info
     * @param  list<Booking>  $bookings  up to three, shown inline
     */
    public function __construct(
        public readonly string $key,
        public readonly string $tone,
        public readonly string $text,
        public readonly string $url,
        public readonly array $bookings = [],
    ) {}
}
