<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case GCash = 'gcash';
    case Maya = 'maya';
    case Card = 'card';   // in the schema (thesis Table 5.6) but not offered
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::GCash => 'GCash',
            self::Maya => 'Maya',
            self::Card => 'Card',
            self::Cash => 'Cash',
        };
    }

    /** Methods guests may choose online (CLAUDE.md: GCash and Maya only). */
    public static function online(): array
    {
        return [self::GCash, self::Maya];
    }

    public function isOnline(): bool
    {
        return in_array($this, self::online(), true);
    }
}
