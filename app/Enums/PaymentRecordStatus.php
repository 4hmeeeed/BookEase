<?php

namespace App\Enums;

/** payments.status (settlement of one payment row). */
enum PaymentRecordStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Paid => 'badge-confirmed',
            self::Pending => 'badge-pending',
            self::Refunded => 'badge-checked-in',
            default => 'badge-cancelled',
        };
    }
}
