<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Jenis cuti/izin awal. Kuota mengikuti umum di Indonesia (cuti tahunan 12 hari);
 * sesuaikan dengan peraturan perusahaan.
 */
class LeaveTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['CT', 'Cuti Tahunan', true, 12, false],
            ['SKT', 'Sakit', false, 0, true],
            ['IZN', 'Izin Pribadi', false, 0, false],
            ['CP', 'Cuti Penting (menikah, duka, dll.)', false, 0, false],
        ];

        foreach ($types as [$code, $name, $usesBalance, $quota, $attachment]) {
            LeaveType::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'uses_balance' => $usesBalance,
                'default_quota' => $quota,
                'requires_attachment' => $attachment,
            ]);
        }
    }
}
