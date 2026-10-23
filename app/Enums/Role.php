<?php

namespace App\Enums;

/**
 * Role HUMANIX. Nilai enum = nama role di tabel roles (spatie).
 */
enum Role: string
{
    case Administrator = 'Administrator';
    case Hrd = 'HRD';
    case Manager = 'Manager';
    case Karyawan = 'Karyawan';

    /** Nama route dashboard tujuan setelah login. */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Administrator => 'admin.dashboard',
            self::Hrd => 'hr.dashboard',
            self::Manager => 'manager.dashboard',
            self::Karyawan => 'me.dashboard',
        };
    }

    /**
     * Izin bawaan tiap role. Administrator sengaja tidak diberi akses
     * data gaji dan kinerja (bisa ditambahkan manual bila kebijakan berubah).
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        $self = ['attendance.self', 'leave.self', 'overtime.self', 'payroll.self', 'performance.self', 'organization.view'];

        return match ($this) {
            self::Administrator => [
                'users.manage', 'settings.manage', 'audit.view',
                'organization.view', 'departments.manage', 'positions.manage',
                'employees.view',
            ],
            self::Hrd => [
                ...$self,
                'employees.view', 'employees.manage',
                'recruitment.view', 'recruitment.manage',
                'attendance.view', 'attendance.manage', 'attendance.kiosk',
                'leave.view', 'leave.approve',
                'overtime.view', 'overtime.approve',
                'payroll.view', 'payroll.manage', 'payroll.approve',
                'performance.view', 'performance.manage',
                'reports.view',
                'departments.manage', 'positions.manage',
            ],
            self::Manager => [
                ...$self,
                'employees.view',
                'recruitment.view',
                'attendance.view',
                'leave.view', 'leave.approve',
                'overtime.view', 'overtime.approve',
                'performance.view', 'performance.review',
                'reports.view',
            ],
            self::Karyawan => $self,
        };
    }

    /** @return list<string> */
    public static function allPermissions(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $role) => $role->defaultPermissions())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
