<?php

namespace App\Enums;

enum PaymentKind: string
{
    case Down = 'down';
    case Balance = 'balance';
    case Full = 'full';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Down => 'Down payment',
            self::Balance => 'Balance',
            self::Full => 'Full payment',
            self::Refund => 'Refund',
        };
    }
}
