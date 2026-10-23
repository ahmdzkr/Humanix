<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PayrollStatus;
use App\Enums\RequestStatus;
use App\Exports\LeaveOvertimeExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Support\FilePreview;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan bulanan: ringkasan angka + unduhan Excel sesuai izin pengguna.
 * HRD melihat seluruh perusahaan; Manager hanya timnya (bawahan langsung & tidak langsung).
 */
class ReportController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $month = $this->month($request);
        $scope = $this->scope($user);
        $rows = $this->leaveOvertimeRows($month, $scope);

        return Inertia::render('reports/index', [
            'filters' => ['month' => $month],
            'scopeLabel' => $scope === null ? 'Seluruh perusahaan' : 'Tim Anda',
            'summary' => [
                'headcount' => $this->metrics->headcount($scope),
                'attendance' => $this->metrics->attendanceMonth($month, $scope),
                'leave_days' => array_sum(array_column($rows, 3)),
                'overtime_hours' => round(array_sum(array_column($rows, 5)), 1),
            ],
            'rows' => array_map(fn ($r) => [
                'nik' => $r[0], 'name' => $r[1], 'department' => $r[2],
                'leave_days' => $r[3], 'leave_requests' => $r[4], 'overtime_hours' => $r[5], 'overtime_requests' => $r[6],
            ], $rows),
            'downloads' => [
                'leave_overtime' => route('reports.leave-overtime', ['month' => $month], absolute: false),
                'employees' => $user->can('employees.manage') ? route('employees.export', absolute: false) : null,
                'attendance' => $user->can('attendance.manage') ? route('attendance.export', ['mode' => 'monthly', 'month' => $month], absolute: false) : null,
            ],
            'payrollPeriods' => $user->can('payroll.view')
                ? PayrollPeriod::query()->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Closed->value])
                    ->latest('start_date')->limit(12)->get()
                    ->map(fn (PayrollPeriod $p) => [
                        'id' => $p->id, 'label' => $p->label(), 'status' => $p->status->label(),
                        'url' => route('payroll.periods.export', $p, absolute: false),
                    ])
                : [],
        ]);
    }

    public function leaveOvertime(Request $request): BinaryFileResponse|JsonResponse
    {
        $month = $this->month($request);

        return FilePreview::excel(
            new LeaveOvertimeExport($this->leaveOvertimeRows($month, $this->scope($request->user())), $month),
            "rekap-cuti-lembur-{$month}.xlsx",
        );
    }

    /** Bulan "Y-m" dari query; default bulan berjalan (WIB). */
    private function month(Request $request): string
    {
        $month = (string) $request->query('month', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : CarbonImmutable::now(config('app.display_timezone'))->format('Y-m');
    }

    /** @return list<int>|null null = seluruh perusahaan */
    private function scope(User $user): ?array
    {
        if ($user->can('employees.manage')) {
            return null;
        }

        return $user->employee?->subordinateIds() ?? [];
    }

    /**
     * Satu baris per karyawan aktif: hari cuti disetujui (berdasarkan bulan mulai, sama dengan dashboard),
     * jumlah pengajuan cuti, jam lembur disetujui, jumlah pengajuan lembur (pengajuan dibatalkan tidak dihitung).
     *
     * @param  list<int>|null  $scope
     * @return list<array<int, string|int|float|null>>
     */
    private function leaveOvertimeRows(string $month, ?array $scope): array
    {
        $start = CarbonImmutable::parse("{$month}-01");
        [$from, $to] = [$start->toDateString(), $start->endOfMonth()->toDateString()];

        $leave = LeaveRequest::query()->whereBetween('start_date', [$from, $to])
            ->where('status', '!=', RequestStatus::Cancelled->value)
            ->get(['employee_id', 'days', 'status'])->groupBy('employee_id');
        $overtime = OvertimeRequest::query()->whereBetween('work_date', [$from, $to])
            ->where('status', '!=', RequestStatus::Cancelled->value)
            ->get(['employee_id', 'duration_minutes', 'status'])->groupBy('employee_id');

        return Employee::query()
            ->where('is_active', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope ?: [0]))
            ->with('department:id,name')->orderBy('full_name')
            ->get(['id', 'nik', 'full_name', 'department_id'])
            ->map(function (Employee $e) use ($leave, $overtime) {
                $l = $leave->get($e->id, collect());
                $o = $overtime->get($e->id, collect());
                $approved = fn ($r) => $r->status === RequestStatus::Approved;

                return [
                    $e->nik, $e->full_name, $e->department?->name,
                    (int) $l->filter($approved)->sum('days'), $l->count(),
                    round($o->filter($approved)->sum('duration_minutes') / 60, 1), $o->count(),
                ];
            })->values()->all();
    }
}
