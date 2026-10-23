<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Contoh struktur organisasi untuk pengembangan lokal.
 */
class OrganizationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            // kode, nama, induk
            ['OPS', 'Operasional', null],
            ['HR', 'Human Resources', null],
            ['FIN', 'Keuangan', null],
            ['IT', 'Teknologi Informasi', null],
            ['PRD', 'Produksi', 'OPS'],
            ['LOG', 'Logistik', 'OPS'],
        ];

        foreach ($departments as [$code, $name, $parent]) {
            Department::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'parent_id' => $parent ? Department::query()->where('code', $parent)->value('id') : null,
            ]);
        }

        $positions = [
            // kode, nama, kode departemen, level
            ['DIR', 'Direktur Utama', null, 10],
            ['OPS-MGR', 'Manager Operasional', 'OPS', 7],
            ['HR-MGR', 'Manager HRD', 'HR', 7],
            ['HR-STF', 'Staff HR', 'HR', 3],
            ['FIN-MGR', 'Manager Keuangan', 'FIN', 7],
            ['FIN-STF', 'Staff Akuntansi', 'FIN', 3],
            ['IT-SPV', 'Supervisor IT', 'IT', 5],
            ['IT-DEV', 'Programmer', 'IT', 3],
            ['PRD-SPV', 'Supervisor Produksi', 'PRD', 5],
            ['PRD-OPR', 'Operator Produksi', 'PRD', 1],
            ['LOG-STF', 'Staff Gudang', 'LOG', 2],
        ];

        foreach ($positions as [$code, $name, $department, $level]) {
            Position::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'department_id' => $department ? Department::query()->where('code', $department)->value('id') : null,
                'level' => $level,
            ]);
        }
    }
}
