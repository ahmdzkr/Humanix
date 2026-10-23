<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Support\FilePreview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * Rekap absensi untuk HRD (semua) dan Manager (timnya). Karyawan diarahkan ke Absensi Saya.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('attendance.view')) {
            return redirect()->route('attendance.mine');
        }

        $filters = $this->filters($request);
        $employees = $this->visibleEmployees($request, $filters);

        $props = [
            'filters' => $filters,
            'departmentOptions' => Department::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => AttendanceStatus::options(),
            'employeeOptions' => $user->can('attendance.manage')
                ? Employee::query()->visibleTo($user)->orderBy('full_name')->get(['id', 'full_name', 'nik'])
                : [],
            'can' => [
                'correct' => $user->can('attendance.manage'),
                'export' => $user->can('attendance.manage'),
                'kiosk' => $user->can('attendance.kiosk'),
            ],
        ];

        if ($filters['mode'] === 'monthly') {
            return Inertia::render('attendance/index', [...$props, 'monthly' => $this->monthly($employees, $filters['month']), 'daily' => null]);
        }

        return Inertia::render('attendance/index', [...$props, 'daily' => $this->daily($employees, $filters), 'monthly' => null]);
    }

    public function mine(Request $request): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, 'Akun Anda belum terhubung dengan data karyawan.');

        $month = $this->month($request->query('month'));
        [$from, $to] = [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];

        $records = $employee->attendanceRecords()
            ->whereBetween('work_date', [$from, $to])
            ->orderByDesc('work_date')
            ->get();

        return Inertia::render('attendance/mine', [
            'month' => $month->format('Y-m'),
            'records' => $records->map(fn (AttendanceRecord $r) => $this->row($r))->values(),
            'summary' => $this->summarize($records),
            'schedule' => $employee->scheduleOn(now(config('app.display_timezone'))->toDateString())?->label(),
        ]);
    }

    public function correct(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.manage'), 403);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in_time' => ['nullable', 'date_format:H:i', Rule::requiredIf(fn () => in_array($request->input('status'), ['present', 'late'], true))],
            'check_out_time' => ['nullable', 'date_format:H:i', 'after:check_in_time'],
            'note' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'check_in_time.required' => 'Jam masuk wajib diisi untuk status Hadir atau Terlambat.',
            'check_out_time.after' => 'Jam pulang harus setelah jam masuk.',
        ], [
            'employee_id' => 'karyawan',
            'work_date' => 'tanggal',
            'check_in_time' => 'jam masuk',
            'check_out_time' => 'jam pulang',
            'reason' => 'alasan koreksi',
            'note' => 'catatan',
        ]);

        $employee = Employee::query()->visibleTo($request->user())->findOrFail($data['employee_id']);

        $this->attendance->correct($employee, CarbonImmutable::parse($data['work_date'])->toDateString(), $data, $data['reason'], $request->user());

        return back()->with('success', "Absensi {$employee->full_name} berhasil dikoreksi.");
    }

    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        abort_unless($request->user()->can('attendance.manage'), 403);

        $filters = $this->filters($request);
        $employees = $this->visibleEmployees($request, $filters);

        $export = $filters['mode'] === 'monthly'
            ? AttendanceExport::monthly($this->monthlyRows($employees->get(), $filters['month']), $filters['month'])
            : AttendanceExport::daily($this->dailyQuery($employees, $filters)->get()->map(fn ($r) => $this->row($r))->all(), $filters['date']);

        $suffix = $filters['mode'] === 'monthly' ? $filters['month'] : $filters['date'];

        return FilePreview::excel($export, "absensi-{$suffix}.xlsx");
    }

    /**
     * @return array{mode: string, date: string, month: string, search: string, department: string, status: string}
     */
    private function filters(Request $request): array
    {
        $today = now(config('app.display_timezone'))->toDateString();
        $date = $request->query('date');

        return [
            'mode' => $request->query('mode') === 'monthly' ? 'monthly' : 'daily',
            'date' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= $today ? $date : $today,
            'month' => $this->month($request->query('month'))->format('Y-m'),
            'search' => trim((string) $request->query('search', '')),
            'department' => $request->integer('department') ? (string) $request->integer('department') : '',
            'status' => AttendanceStatus::tryFrom((string) $request->query('status'))?->value ?? '',
        ];
    }

    private function month(mixed $value): CarbonImmutable
    {
        $tz = config('app.display_timezone');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            $month = CarbonImmutable::createFromFormat('Y-m-d', "{$value}-01", $tz);

            if ($month && $month->lte(now($tz))) {
                return $month->startOfMonth();
            }
        }

        return CarbonImmutable::now($tz)->startOfMonth();
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Employee>
     */
    private function visibleEmployees(Request $request, array $filters): Builder
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$filters['search']}%")
                ->orWhere('nik', 'like', "%{$filters['search']}%")))
            ->when($filters['department'] !== '', fn ($q) => $q->where('department_id', $filters['department']));
    }

    /**
     * @param  Builder<Employee>  $employees
     * @param  array<string, string>  $filters
     * @return Builder<AttendanceRecord>
     */
    private function dailyQuery(Builder $employees, array $filters): Builder
    {
        return AttendanceRecord::query()
            ->whereDate('work_date', $filters['date'])
            ->whereIn('employee_id', (clone $employees)->select('id'))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->with(['employee:id,full_name,nik,department_id', 'employee.department:id,name', 'corrections.user:id,name'])
            ->orderBy(Employee::select('full_name')->whereColumn('employees.id', 'attendance_records.employee_id'));
    }

    /**
     * @param  Builder<Employee>  $employees
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function daily(Builder $employees, array $filters): array
    {
        $records = $this->dailyQuery($employees, $filters)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AttendanceRecord $r) => $this->row($r));

        $all = AttendanceRecord::query()
            ->whereDate('work_date', $filters['date'])
            ->whereIn('employee_id', (clone $employees)->select('id'))
            ->get(['status']);

        $summary = $this->summarize($all);
        $date = CarbonImmutable::parse($filters['date'], config('app.display_timezone'));

        // Karyawan aktif yang dijadwalkan kerja hari itu tetapi belum ada catatan (sebelum job harian berjalan).
        $summary['not_yet'] = (clone $employees)
            ->where('is_active', true)
            ->whereDoesntHave('attendanceRecords', fn ($q) => $q->whereDate('work_date', $filters['date']))
            ->get()
            ->filter(fn (Employee $e) => $e->scheduleOn($filters['date'])?->isWorkDay($date) ?? false)
            ->count();

        return ['records' => $records, 'summary' => $summary];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array<string, mixed>
     */
    private function monthly(Builder $employees, string $month): array
    {
        $page = (clone $employees)->with('department:id,name')->orderBy('full_name')->paginate(15)->withQueryString();

        return [
            'rows' => $page->setCollection(collect($this->monthlyRows($page->getCollection(), $month))),
        ];
    }

    /**
     * @param  iterable<Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function monthlyRows(iterable $employees, string $month): array
    {
        $start = CarbonImmutable::parse("{$month}-01");
        $employees = collect($employees);

        $records = AttendanceRecord::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->get(['employee_id', 'status', 'late_minutes'])
            ->groupBy('employee_id');

        return $employees->map(function (Employee $e) use ($records) {
            $own = $records->get($e->id, collect());

            return [
                'employee_id' => $e->id,
                'full_name' => $e->full_name,
                'nik' => $e->nik,
                'department' => $e->department?->name,
                ...$this->summarize($own),
                'late_minutes' => (int) $own->sum('late_minutes'),
            ];
        })->values()->all();
    }

    /**
     * @param  iterable<AttendanceRecord>  $records
     * @return array{present: int, late: int, absent: int, leave: int}
     */
    private function summarize(iterable $records): array
    {
        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0];

        foreach ($records as $record) {
            $counts[$record->status->value]++;
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function row(AttendanceRecord $r): array
    {
        $tz = config('app.display_timezone');

        return [
            'id' => $r->id,
            'employee_id' => $r->employee_id,
            'full_name' => $r->employee?->full_name,
            'nik' => $r->employee?->nik,
            'department' => $r->employee?->department?->name,
            'work_date' => $r->work_date->format('Y-m-d'),
            'check_in' => $r->check_in_at?->setTimezone($tz)->format('H:i'),
            'check_out' => $r->check_out_at?->setTimezone($tz)->format('H:i'),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'late_minutes' => $r->late_minutes,
            'source' => $r->source,
            'note' => $r->note,
            'corrections' => $r->relationLoaded('corrections')
                ? $r->corrections->map(fn ($c) => [
                    'reason' => $c->reason,
                    'actor' => $c->user?->name ?? 'Sistem',
                    'old' => $c->old_values,
                    'new' => $c->new_values,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
