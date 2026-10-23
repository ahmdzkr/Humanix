<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\OvertimeService;

class OvertimeRequestPolicy
{
    public function view(User $user, OvertimeRequest $overtime): bool
    {
        if ($user->employee?->id === $overtime->employee_id) {
            return true;
        }

        if ($overtime->approvalSteps()->where('assigned_user_id', $user->id)->exists()) {
            return true;
        }

        // Lembur HRD yang diputuskan Manager mana pun (langkah "managers").
        if (app(ApprovalService::class)->isApproverOf($user, $overtime, OvertimeService::PERMISSION)) {
            return true;
        }

        return $user->can('overtime.view')
            && Employee::query()->visibleTo($user)->whereKey($overtime->employee_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('overtime.self') && $user->employee?->is_active === true;
    }

    public function decide(User $user, OvertimeRequest $overtime): bool
    {
        return app(OvertimeService::class)->canDecide($user, $overtime);
    }

    public function cancel(User $user, OvertimeRequest $overtime): bool
    {
        return $user->employee?->id === $overtime->employee_id && $overtime->isPending();
    }
}
