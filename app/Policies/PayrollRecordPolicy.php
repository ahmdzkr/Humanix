<?php

namespace App\Policies;

use App\Models\PayrollRecord;
use App\Models\User;

class PayrollRecordPolicy
{
    /**
     * HRD (payroll.view) melihat semua slip. Karyawan hanya slip miliknya,
     * dan hanya setelah periode disetujui. Administrator & Manager tidak melihat gaji orang lain.
     */
    public function view(User $user, PayrollRecord $record): bool
    {
        if ($user->can('payroll.view')) {
            return true;
        }

        return $user->can('payroll.self')
            && $user->employee?->id === $record->employee_id
            && $record->period->status->isPublished();
    }
}
