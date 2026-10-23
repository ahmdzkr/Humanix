<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use Illuminate\Database\Seeder;

/**
 * Struktur gaji contoh untuk karyawan demo (hanya lokal). Angka BPJS/PPh di sini hanya contoh,
 * bukan tarif resmi.
 */
class PayrollDemoSeeder extends Seeder
{
    public function run(): void
    {
        $component = fn (string $code) => SalaryComponent::query()->where('code', $code)->value('id');

        $bases = [
            'EMP-0001' => ['12000000', '2000000'],
            'EMP-0002' => ['13500000', '2500000'],
            'EMP-0003' => ['8000000', '1000000'],
            'EMP-0004' => ['5200000', null],
            'EMP-0005' => ['5500000', null],
            'EMP-0006' => ['9000000', null],
        ];

        foreach ($bases as $nik => [$base, $positionAllowance]) {
            $employee = Employee::query()->where('nik', $nik)->first();

            if ($employee === null || $employee->salaryStructures()->exists()) {
                continue;
            }

            $structure = SalaryStructure::create([
                'employee_id' => $employee->id,
                'base_salary' => $base,
                'effective_from' => '2026-01-01',
                'notes' => 'Data demo',
            ]);

            $items = [
                ['salary_component_id' => $component('TJ-TRP'), 'amount' => '600000'],
                ['salary_component_id' => $component('TJ-MKN'), 'amount' => '550000'],
                ['salary_component_id' => $component('BPJS-KES'), 'rate' => '1'],
                ['salary_component_id' => $component('BPJS-JHT'), 'rate' => '2'],
            ];

            if ($positionAllowance) {
                $items[] = ['salary_component_id' => $component('TJ-JBT'), 'amount' => $positionAllowance];
            }

            $structure->items()->createMany($items);
        }
    }
}
