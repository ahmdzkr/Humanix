<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    /** Ditentukan oleh scope visibleTo (HRD/Admin semua, Manager bawahan, lainnya diri sendiri). */
    public function view(User $user, Employee $employee): bool
    {
        if ($user->employee?->id === $employee->id) {
            return true;
        }

        return $user->can('employees.view')
            && Employee::query()->visibleTo($user)->whereKey($employee->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage');
    }

    /** Nonaktifkan / aktifkan kembali. HRD tidak bisa menonaktifkan dirinya sendiri. */
    public function changeStatus(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage') && $user->employee?->id !== $employee->id;
    }

    public function rotateQr(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage');
    }

    public function export(User $user): bool
    {
        return $user->can('employees.manage');
    }

    /** Ganti role akun: hanya pemegang users.manage (Administrator), bukan untuk akunnya sendiri. */
    public function changeRole(User $user, Employee $employee): bool
    {
        return $user->can('users.manage') && $user->id !== $employee->user_id;
    }

    /** Lihat rekening (tersamar): HRD saja. */
    public function viewBankAccount(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage') || $user->employee?->id === $employee->id;
    }

    /** Cetak ID Card: HRD, atau karyawan untuk dirinya sendiri. */
    public function printIdCard(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage') || $user->employee?->id === $employee->id;
    }
}
