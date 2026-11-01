<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';

    /**
     * Statuses whose dates are held. Rejected, cancelled, and checked-out
     * bookings release their dates.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed, self::CheckedIn];
    }

    /**
     * @return list<string>
     */
    public static function blockingValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::blocking());
    }

    public function holdsDates(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out',
            default => ucfirst($this->value),
        };
    }

    /** CSS badge modifier from resources/css/app.css */
    public function badgeClass(): string
    {
        return 'badge-'.str_replace('_', '-', $this->value);
    }
}
