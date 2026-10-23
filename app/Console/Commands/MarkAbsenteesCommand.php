<?php

namespace App\Console\Commands;

use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Dijalankan terjadwal setiap malam: menandai karyawan terjadwal yang tidak absen.
 */
class MarkAbsenteesCommand extends Command
{
    protected $signature = 'humanix:mark-absent {--date= : Tanggal kerja (Y-m-d, WIB). Default: hari ini}';

    protected $description = 'Tandai Tidak Hadir / Cuti untuk karyawan terjadwal yang belum punya catatan absensi';

    public function handle(AttendanceService $attendance): int
    {
        $tz = config('app.display_timezone');
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $tz)->toDateString()
            : CarbonImmutable::now($tz)->toDateString();

        $counts = $attendance->markAbsentees($date);

        $this->info("{$date}: {$counts['absent']} tidak hadir, {$counts['leave']} cuti/izin ditandai.");

        return self::SUCCESS;
    }
}
