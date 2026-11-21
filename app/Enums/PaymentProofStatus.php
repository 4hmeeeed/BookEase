<?php

namespace App\Enums;

/** payment_proofs.status: a guest's GCash/Maya transfer, before and after staff check it. */
enum PaymentProofStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for the resort to check',
            self::Verified => 'Verified',
            self::Rejected => 'Not accepted',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-pending',
            self::Verified => 'badge-confirmed',
            self::Rejected => 'badge-cancelled',
        };
    }
}
