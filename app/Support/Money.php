<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Peso amounts are always handled as numeric strings with 2 decimal places
 * and computed with bcmath — never floats.
 */
final class Money
{
    public const SCALE = 2;

    /**
     * Normalise any numeric string/int to a 2-decimal string, e.g. "1500" → "1500.00".
     */
    public static function normalize(string|int $amount): string
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return bcadd($amount, '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $amount, string|int $multiplier): string
    {
        return bcmul($amount, (string) $multiplier, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    /**
     * Format for display without converting to float: "1234567.5" → "₱1,234,567.50".
     */
    public static function format(string|int $amount, bool $withSymbol = true): string
    {
        $normalized = self::normalize($amount);

        $negative = str_starts_with($normalized, '-');
        [$whole, $cents] = explode('.', ltrim($normalized, '-'));

        $grouped = ltrim(strrev(implode(',', str_split(strrev($whole), 3))), ',');

        return ($negative ? '-' : '').($withSymbol ? '₱' : '').$grouped.'.'.$cents;
    }
}
