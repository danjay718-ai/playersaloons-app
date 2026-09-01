<?php

declare(strict_types=1);

namespace App\Shared\Support;

use InvalidArgumentException;

final class DecimalMoney
{
    public static function toMinor(string|int|float $amount): int
    {
        $normalized = is_float($amount)
            ? number_format($amount, 2, '.', '')
            : trim((string) $amount);
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Money must have at most two decimal places.');
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$minor : $minor;
    }

    public static function format(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percentage(int $minor, int $basisPoints): int
    {
        return intdiv(($minor * $basisPoints) + 5000, 10000);
    }
}
