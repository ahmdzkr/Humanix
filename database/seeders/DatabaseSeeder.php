<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Data wajib (juga di produksi).
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
            AttendanceDefaultsSeeder::class,
            LeaveTypesSeeder::class,
            SalaryComponentsSeeder::class,
        ]);

        // Akun dan data demo hanya untuk lingkungan lokal (kata sandi factory: "password").
        if (app()->isLocal()) {
            $this->call(OrganizationDemoSeeder::class);

            $demoUsers = [
                ['Admin HUMANIX', 'admin@humanix.test', Role::Administrator],
                ['Rina Hartati', 'hrd@humanix.test', Role::Hrd],
                ['Bayu Pratama', 'manager@humanix.test', Role::Manager],
                ['Siti Rahmawati', 'karyawan@humanix.test', Role::Karyawan],
            ];

            foreach ($demoUsers as [$name, $email, $role]) {
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    User::factory()->raw(['name' => $name, 'email' => $email]),
                );
                $user->syncRoles([$role->value]);
            }

            $this->call([EmployeeDemoSeeder::class, AttendanceDemoSeeder::class, PayrollDemoSeeder::class, RecruitmentDemoSeeder::class, PerformanceDemoSeeder::class]);
        }
    }
}
