<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Karyawan demo untuk pengembangan lokal. Akun demo HRD/Manager/Karyawan dihubungkan
 * ke data karyawan; Administrator sengaja tidak punya data karyawan.
 */
class EmployeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $dept = fn (string $code) => Department::query()->where('code', $code)->value('id');
        $pos = fn (string $code) => Position::query()->where('code', $code)->value('id');

        $make = function (string $email, string $name, string $nik, string $deptCode, string $posCode, ?Employee $manager, Role $role, EmploymentStatus $status = EmploymentStatus::Permanent) use ($dept, $pos): Employee {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                User::factory()->raw(['name' => $name, 'email' => $email]),
            );
            $user->syncRoles([$role->value]);

            return Employee::query()->firstOrCreate(['user_id' => $user->id], [
                'nik' => $nik,
                'full_name' => $name,
                'gender' => in_array($name, ['Rina Hartati', 'Siti Rahmawati', 'Dewi Lestari'], true) ? 'P' : 'L',
                'department_id' => $dept($deptCode),
                'position_id' => $pos($posCode),
                'manager_id' => $manager?->id,
                'employment_status' => $status,
                'join_date' => now()->subYears(2)->toDateString(),
                'contract_end_date' => $status === EmploymentStatus::Contract ? now()->addYear()->toDateString() : null,
                'phone' => '0812'.random_int(10000000, 99999999),
            ]);
        };

        $hrd = $make('hrd@humanix.test', 'Rina Hartati', 'EMP-0001', 'HR', 'HR-MGR', null, Role::Hrd);
        $manager = $make('manager@humanix.test', 'Bayu Pratama', 'EMP-0002', 'OPS', 'OPS-MGR', null, Role::Manager);
        $supervisor = $make('agus@humanix.test', 'Agus Setiawan', 'EMP-0003', 'PRD', 'PRD-SPV', $manager, Role::Karyawan);
        $make('karyawan@humanix.test', 'Siti Rahmawati', 'EMP-0004', 'PRD', 'PRD-OPR', $supervisor, Role::Karyawan, EmploymentStatus::Contract);
        $make('dewi@humanix.test', 'Dewi Lestari', 'EMP-0005', 'HR', 'HR-STF', $hrd, Role::Karyawan, EmploymentStatus::Probation);
        $make('eko@humanix.test', 'Eko Nugroho', 'EMP-0006', 'IT', 'IT-DEV', null, Role::Karyawan);

        Department::query()->where('code', 'HR')->update(['head_employee_id' => $hrd->id]);
        Department::query()->where('code', 'OPS')->update(['head_employee_id' => $manager->id]);
    }
}
