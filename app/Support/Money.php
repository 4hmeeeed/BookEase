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

    public static function isPositive(string $amount): bool
    {
        return bccomp($amount, '0', self::SCALE) > 0;
    }

    public static function min(string $a, string $b): string
    {
        return self::compare($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    /**
     * $percent % of $amount, rounded half-up to centavos. percent("7501.50", 50) → "3750.75"
     */
    public static function percentOf(string $amount, string|int $percent): string
    {
        $raw = bcdiv(bcmul($amount, (string) $percent, 6), '100', 6);

        return self::roundHalfUp($raw);
    }

    /** "1500.50" → 150050 (gateways take integer centavos). */
    public static function toCentavos(string $amount): int
    {
        return (int) bcmul(self::normalize($amount), '100', 0);
    }

    /** 150050 → "1500.50" */
    public static function fromCentavos(int|string $centavos): string
    {
        return bcdiv((string) $centavos, '100', self::SCALE);
    }

    private static function roundHalfUp(string $value): string
    {
        $offset = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $offset, 6), '0', self::SCALE);
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
