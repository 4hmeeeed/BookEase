<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** One line of the owner's "Recent activity" feed. */
final class ActivityEntry
{
    /**
     * @param  string  $tone  attention | money | info
     */
    public function __construct(
        public readonly CarbonImmutable $at,
        public readonly string $text,
        public readonly ?string $reference,
        public readonly string $tone,
    ) {}
}
