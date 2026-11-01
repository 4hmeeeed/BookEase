<?php

namespace App\Support;

/**
 * Human-friendly booking reference: "LBN-7KQ4XM".
 * Alphabet omits 0/O, 1/I/L, and U so codes are easy to read over the phone.
 * 30^6 ≈ 729 million combinations; uniqueness is still enforced by the DB.
 */
final class ReferenceCode
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(string $prefix = 'LBN', int $length = 6): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return "{$prefix}-{$chars}";
    }
}
