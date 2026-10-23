<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

/**
 * Jadwal kerja default dan hari libur nasional bertanggal tetap.
 * Libur yang tanggalnya berubah tiap tahun (Idulfitri, Iduladha, Nyepi, Waisak, Imlek, dll.)
 * dan cuti bersama perlu ditambahkan HRD sesuai SKB pemerintah.
 */
class AttendanceDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        WorkSchedule::query()->firstOrCreate(['name' => 'Reguler'], [
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => null,
            'check_in_opens_minutes' => 120,
            'check_out_closes_minutes' => 360,
        ]);

        $year = (int) now(config('app.display_timezone'))->year;

        foreach ([$year, $year + 1] as $y) {
            foreach ([
                '01-01' => 'Tahun Baru Masehi',
                '05-01' => 'Hari Buruh Internasional',
                '06-01' => 'Hari Lahir Pancasila',
                '08-17' => 'Hari Kemerdekaan Republik Indonesia',
                '12-25' => 'Hari Raya Natal',
            ] as $monthDay => $name) {
                Holiday::query()->firstOrCreate(['date' => "{$y}-{$monthDay}"], ['name' => $name]);
            }
        }
    }
}
