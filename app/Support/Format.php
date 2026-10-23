<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Helper format Indonesia untuk sisi server (PDF slip gaji, ekspor, email).
 * Data waktu disimpan UTC dan ditampilkan dalam zona waktu tampilan (WIB).
 */
class Format
{
    /** Rabu, 7 Oktober 2026 */
    public static function tanggal(CarbonInterface|string $value): string
    {
        return self::toDisplay($value)->locale('id')->translatedFormat('l, j F Y');
    }

    /** 08:00 WIB */
    public static function jam(CarbonInterface|string $value): string
    {
        return self::toDisplay($value)->format('H:i').' WIB';
    }

    /**
     * Rp 10.452.312,14 — untuk nilai uang presisi (string desimal), sen ditampilkan bila bukan nol.
     * Tidak membulatkan, supaya rincian slip selalu cocok dengan totalnya.
     */
    public static function uang(string|int|null $value): string
    {
        $raw = Money::of($value);
        $negative = str_starts_with($raw, '-');
        [$int, $cents] = explode('.', ltrim($raw, '-'));
        $grouped = number_format((float) $int, 0, ',', '.');

        return ($negative ? '-' : '').'Rp '.$grouped.($cents !== '00' ? ",{$cents}" : '');
    }

    /** Rp 650.000.000 */
    public static function rupiah(int|float|string|null $value): string
    {
        $angka = (int) round((float) $value);
        $prefix = $angka < 0 ? '-' : '';

        return $prefix.'Rp '.number_format(abs($angka), 0, ',', '.');
    }

    private static function toDisplay(CarbonInterface|string $value): Carbon
    {
        $date = $value instanceof CarbonInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'UTC');

        return $date->setTimezone(config('app.display_timezone', 'Asia/Jakarta'));
    }
}
