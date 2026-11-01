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

    /**
     * The lifecycle state machine.
     *
     *   pending ──approve──▶ confirmed ──check in──▶ checked_in ──check out──▶ checked_out
     *      │                     │
     *      ├──reject──▶ rejected │
     *      └──cancel──▶ cancelled ◀──cancel──┘
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Cancelled],
            self::CheckedIn => [self::CheckedOut],
            self::Rejected, self::Cancelled, self::CheckedOut => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
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
