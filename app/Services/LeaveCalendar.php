<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;

/**
 * Menjawab "apakah karyawan sedang cuti/izin yang disetujui pada tanggal ini?".
 * Dipakai absensi (scan & penandaan tidak hadir).
 */
class LeaveCalendar
{
    public function isOnApprovedLeave(Employee $employee, string $date): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', RequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
