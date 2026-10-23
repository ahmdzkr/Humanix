<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LeaveService;

class LeaveRequestPolicy
{
    /** Pemilik, penyetuju yang ditugaskan, atau pemegang leave.view yang boleh melihat karyawannya. */
    public function view(User $user, LeaveRequest $leave): bool
    {
        if ($user->employee?->id === $leave->employee_id) {
            return true;
        }

        if ($leave->approvalSteps()->where('assigned_user_id', $user->id)->exists()) {
            return true;
        }

        // Cuti HRD yang diputuskan Manager mana pun (langkah "managers").
        if (app(ApprovalService::class)->isApproverOf($user, $leave, LeaveService::PERMISSION)) {
            return true;
        }

        return $user->can('leave.view')
            && Employee::query()->visibleTo($user)->whereKey($leave->employee_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('leave.self') && $user->employee?->is_active === true;
    }

    public function decide(User $user, LeaveRequest $leave): bool
    {
        return app(LeaveService::class)->canDecide($user, $leave);
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        return $user->employee?->id === $leave->employee_id && $leave->isPending();
    }
}
