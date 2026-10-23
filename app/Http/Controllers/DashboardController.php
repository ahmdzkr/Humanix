<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Services\DashboardMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    /**
     * /dashboard: arahkan ke dashboard sesuai role pengguna.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $url = $request->user()->dashboardUrl();

        abort_if($url === null, 403, 'Akun Anda belum diberi peran. Hubungi Administrator.');

        return redirect($url);
    }

    public function admin(): Response
    {
        return Inertia::render('dashboard/admin', ['metrics' => $this->metrics->admin()]);
    }

    /**
     * Dashboard HRD. Filter: departemen (membatasi semua metrik karyawan) dan rentang tren (7/30 hari).
     */
    public function hr(Request $request): Response
    {
        $department = $request->integer('department') ?: null;
        $days = $request->integer('days') === 30 ? 30 : 7;
        $scope = $department ? Employee::query()->where('department_id', $department)->pluck('id')->all() : null;
        $today = $this->metrics->today()->toDateString();
        $pending = $this->metrics->pendingRequests($scope);

        return Inertia::render('dashboard/hr', [
            'filters' => ['department' => $department ? (string) $department : '', 'days' => (string) $days],
            'departmentOptions' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'kpi' => [
                'headcount' => $this->metrics->headcount($scope),
                'today' => $this->metrics->attendanceOn($today, $scope),
                'pending_leave' => $pending['leave'],
                'pending_overtime' => $pending['overtime'],
            ],
            'attendanceTrend' => $this->metrics->attendanceTrend($days, $scope),
            'departments' => $this->metrics->departmentDistribution($scope),
            'employmentStatuses' => $this->metrics->employmentStatusDistribution($scope),
            'leaveOvertime' => $this->metrics->leaveOvertimeTrend(6, $scope),
            'awaiting' => $this->metrics->awaitingApprovals($request->user()),
            'payroll' => $this->metrics->payrollSummary(),
            'recruitment' => $this->metrics->recruitmentSummary(),
            'performance' => $this->metrics->performanceSummary($scope),
            'activity' => $this->metrics->recentActivity($scope),
        ]);
    }

    /** Dashboard Manager: hanya data tim (bawahan langsung & tidak langsung). */
    public function manager(Request $request): Response
    {
        $self = $request->user()->employee;
        $scope = $self ? $self->subordinateIds() : [];
        $today = $this->metrics->today()->toDateString();

        $overtimeThisMonth = collect($this->metrics->leaveOvertimeTrend(1, $scope))->first();

        return Inertia::render('dashboard/manager', [
            'kpi' => [
                'team' => $this->metrics->headcount($scope),
                'today' => $this->metrics->attendanceOn($today, $scope),
                'awaiting' => count($this->metrics->awaitingApprovals($request->user(), 100)),
                'overtime_hours' => $overtimeThisMonth['overtime_hours'] ?? 0,
            ],
            'attendanceTrend' => $this->metrics->attendanceTrend(7, $scope),
            'awaiting' => $this->metrics->awaitingApprovals($request->user()),
            'performance' => $this->metrics->performanceSummary($scope),
            'activity' => $this->metrics->recentActivity($scope),
            'personal' => $self ? $this->metrics->personal($self) : null,
        ]);
    }

    /** Dashboard Karyawan: data pribadi. */
    public function employee(Request $request): Response
    {
        $self = $request->user()->employee;

        return Inertia::render('dashboard/employee', [
            'personal' => $self ? $this->metrics->personal($self) : null,
        ]);
    }
}
