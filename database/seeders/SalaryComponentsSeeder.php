<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

/**
 * Komponen gaji awal. Nilai pajak (PPh 21) dan BPJS SENGAJA tidak diisi:
 * tarif dan dasar perhitungannya harus ditetapkan HRD sesuai aturan yang berlaku.
 */
class SalaryComponentsSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // kode, nama, tipe, perhitungan, urutan
            ['TJ-JBT', 'Tunjangan Jabatan', 'earning', 'fixed', 10],
            ['TJ-TRP', 'Tunjangan Transport', 'earning', 'fixed', 20],
            ['TJ-MKN', 'Tunjangan Makan', 'earning', 'fixed', 30],
            ['BPJS-KES', 'BPJS Kesehatan (karyawan)', 'deduction', 'percentage', 10],
            ['BPJS-JHT', 'BPJS Ketenagakerjaan JHT (karyawan)', 'deduction', 'percentage', 20],
            ['BPJS-JP', 'BPJS Ketenagakerjaan JP (karyawan)', 'deduction', 'percentage', 30],
            ['PPH21', 'PPh 21', 'deduction', 'fixed', 40],
        ];

        foreach ($components as [$code, $name, $type, $calculation, $order]) {
            SalaryComponent::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'calculation' => $calculation,
                'sort_order' => $order,
            ]);
        }
    }
}
