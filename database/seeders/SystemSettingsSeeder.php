<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Nilai awal pengaturan sistem. Nilai yang sudah diubah tidak ditimpa.
 */
class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['general', 'company_name', 'Nama perusahaan', 'PT Contoh Indonesia'],
            ['attendance', 'late_tolerance_minutes', 'Toleransi keterlambatan (menit)', 15],
            ['leave', 'approval_flow', 'Alur persetujuan cuti', ['manager', 'hrd']],
            ['overtime', 'max_minutes_per_day', 'Maksimal lembur per hari (menit)', 240],
            // Tarif lembur: gaji pokok ÷ pembagi per jam; jam pertama dan berikutnya dikalikan pengali.
            // Sesuaikan dengan peraturan perusahaan/ketenagakerjaan yang berlaku.
            ['payroll', 'overtime_hourly_divisor', 'Pembagi upah per jam lembur', 173],
            ['payroll', 'overtime_first_hour_multiplier', 'Pengali jam lembur pertama', '1.5'],
            ['payroll', 'overtime_next_hour_multiplier', 'Pengali jam lembur berikutnya', '2'],
            ['recruitment', 'interview_indicators', 'Indikator penilaian wawancara (skala 1–5)', [
                'Kompetensi teknis', 'Komunikasi', 'Pemecahan masalah', 'Kerja sama tim', 'Kesesuaian budaya kerja',
            ]],
        ];

        foreach ($defaults as [$group, $key, $label, $value]) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                ['group' => $group, 'label' => $label, 'value' => $value],
            );
        }
    }
}
