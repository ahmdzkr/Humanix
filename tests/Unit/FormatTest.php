<?php

use App\Support\Format;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('format tanggal Indonesia lengkap dengan nama hari', function () {
    // 7 Oktober 2026 pukul 01:00 UTC = 08:00 WIB di hari yang sama.
    expect(Format::tanggal(Carbon::parse('2026-10-07 01:00:00', 'UTC')))
        ->toBe('Rabu, 7 Oktober 2026');
});

test('tanggal mengikuti zona waktu WIB, bukan UTC', function () {
    // 6 Oktober 20:00 UTC sudah 7 Oktober 03:00 WIB.
    expect(Format::tanggal('2026-10-06 20:00:00'))->toBe('Rabu, 7 Oktober 2026');
});

test('format jam memakai WIB', function () {
    expect(Format::jam('2026-10-07 01:00:00'))->toBe('08:00 WIB');
});

test('format rupiah dengan pemisah ribuan titik', function () {
    expect(Format::rupiah(650000000))->toBe('Rp 650.000.000')
        ->and(Format::rupiah('1250000.60'))->toBe('Rp 1.250.001')
        ->and(Format::rupiah(0))->toBe('Rp 0')
        ->and(Format::rupiah(null))->toBe('Rp 0')
        ->and(Format::rupiah(-15000))->toBe('-Rp 15.000');
});

test('format uang presisi untuk slip: sen dipertahankan, tidak dibulatkan', function () {
    expect(Format::uang('10452312.14'))->toBe('Rp 10.452.312,14')
        ->and(Format::uang('9910000.00'))->toBe('Rp 9.910.000')
        ->and(Format::uang('-250000.50'))->toBe('-Rp 250.000,50')
        ->and(Format::uang(null))->toBe('Rp 0');
});
