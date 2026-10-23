<?php

namespace App\Support;

/**
 * Aritmetika uang presisi (bcmath, string desimal 2 angka). Tidak pernah memakai float.
 */
final class Money
{
    private const SCALE = 2;

    public static function of(int|string|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        // Float hanya diterima dari input yang sudah divalidasi; dibulatkan lewat string.
        $string = is_float($value) ? number_format($value, 4, '.', '') : (string) $value;

        return self::round($string);
    }

    public static function add(string ...$values): string
    {
        return array_reduce($values, fn (string $carry, string $v) => bcadd($carry, $v, self::SCALE), '0.00');
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    /** a × b, dibulatkan setengah ke atas ke 2 desimal. */
    public static function mul(string $a, string $b): string
    {
        return self::round(bcmul($a, $b, 8));
    }

    /** a ÷ b, dibulatkan setengah ke atas ke 2 desimal. */
    public static function div(string $a, string $b): string
    {
        return self::round(bcdiv($a, $b, 8));
    }

    /** Persen dari nilai: rate 1.5 berarti 1,5%. */
    public static function percent(string $value, string $rate): string
    {
        return self::round(bcdiv(bcmul($value, $rate, 8), '100', 8));
    }

    public static function isNegative(string $value): bool
    {
        return bccomp($value, '0', self::SCALE) < 0;
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    /** Pembulatan setengah ke atas (menjauhi nol) ke 2 desimal. */
    public static function round(string $value): string
    {
        $offset = str_starts_with(ltrim($value), '-') ? '-0.005' : '0.005';

        return bcadd($value, $offset, self::SCALE);
    }
}
