<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\SalaryComponent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Semua angka dashboard didefinisikan SATU kali di sini.
 * "Cakupan" (scope) = daftar ID karyawan yang boleh dihitung (null = semua karyawan).
 */
class DashboardMetrics
{
    public function __construct(
        private readonly LeaveCalendar $leaveCalendar,
        private readonly LeaveService $leaves,
        private readonly ApprovalService $approvals,
    ) {}

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.display_timezone'))->startOfDay();
    }

    /**
     * @param  list<int>|null  $scope
     * @return Builder<Employee>
     */
    private function employees(?array $scope): Builder
    {
        // Karyawan aktif yang sudah mulai bekerja (calon karyawan dengan tanggal masuk di masa depan belum dihitung).
        return Employee::query()
            ->where('is_active', true)
            ->whereDate('join_date', '<=', $this->today()->toDateString())
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope === [] ? [0] : $scope));
    }

    /** Jumlah karyawan aktif. */
    public function headcount(?array $scope = null): int
    {
        return $this->employees($scope)->count();
    }

    /**
     * Kehadiran pada satu tanggal.
     * - scheduled: karyawan aktif yang sudah bergabung, punya jadwal, dan tanggal itu hari kerjanya (bukan libur)
     * - present: catatan Hadir/Terlambat; late: Terlambat; absent: Tidak Hadir; leave: cuti disetujui
     * - not_yet: terjadwal, tidak cuti, belum ada catatan
     * - rate: present ÷ (scheduled − leave), 0 bila penyebut 0.
     *
     * @param  list<int>|null  $scope
     * @return array{date: string, scheduled: int, present: int, late: int, absent: int, leave: int, not_yet: int, rate: float}
     */
    public function attendanceOn(string $date, ?array $scope = null): array
    {
        $day = CarbonImmutable::parse($date, config('app.display_timezone'));

        return $this->attendanceRange($day, $day, $scope)[0];
    }

    /**
     * Tren kehadiran N hari terakhir (termasuk hari ini). Hari tanpa karyawan terjadwal tetap ditampilkan (0).
     *
     * @param  list<int>|null  $scope
     * @return list<array{date: string, scheduled: int, present: int, late: int, absent: int, leave: int, not_yet: int, rate: float}>
     */
    public function attendanceTrend(int $days, ?array $scope = null): array
    {
        $end = $this->today();

        return $this->attendanceRange($end->subDays($days - 1), $end, $scope);
    }

    /**
     * Rekap kehadiran satu bulan (sampai hari ini bila bulan berjalan), dari definisi harian yang sama.
     * rate = total hadir ÷ total (terjadwal − cuti) sepanjang bulan; 0 bila penyebut 0.
     *
     * @param  list<int>|null  $scope
     * @return array{month: string, days: int, scheduled: int, present: int, late: int, absent: int, leave: int, rate: float}
     */
    public function attendanceMonth(string $month, ?array $scope = null): array
    {
        $start = CarbonImmutable::parse("{$month}-01", config('app.display_timezone'))->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        $today = $this->today();
        $sum = ['scheduled' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0];
        $days = [];

        if ($start->lte($today)) {
            $days = $this->attendanceRange($start, $end->gt($today) ? $today : $end, $scope);

            foreach ($days as $day) {
                foreach ($sum as $key => $value) {
                    $sum[$key] = $value + $day[$key];
                }
            }
        }

        $expected = $sum['scheduled'] - $sum['leave'];

        return ['month' => $month, 'days' => count($days), ...$sum, 'rate' => $expected > 0 ? round($sum['present'] / $expected * 100, 1) : 0.0];
    }

    /**
     * Definisi tunggal kehadiran harian (lihat attendanceOn). Semua data rentang dimuat sekali —
     * jumlah query tetap, tidak bertambah per hari atau per karyawan.
     *
     * @param  list<int>|null  $scope
     * @return list<array{date: string, scheduled: int, present: int, late: int, absent: int, leave: int, not_yet: int, rate: float}>
     */
    private function attendanceRange(CarbonImmutable $start, CarbonImmutable $end, ?array $scope): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        $employees = $this->employees($scope)->whereDate('join_date', '<=', $to)
            ->with(['schedules' => fn ($q) => $q->whereDate('effective_from', '<=', $to)
                ->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->with('workSchedule')])
            ->get();
        $ids = $employees->modelKeys() ?: [0];

        $holidays = Holiday::query()->whereBetween('date', [$from, $to])->get(['date'])
            ->map(fn ($h) => $h->date->toDateString())->flip();
        $records = AttendanceRecord::query()->whereBetween('work_date', [$from, $to])->whereIn('employee_id', $ids)
            ->get(['employee_id', 'work_date', 'status'])
            ->keyBy(fn ($r) => $r->employee_id.'|'.$r->work_date->toDateString());
        $leaves = LeaveRequest::query()->where('status', RequestStatus::Approved->value)->whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->get(['employee_id', 'start_date', 'end_date'])->groupBy('employee_id');

        $result = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $date = $day->toDateString();
            $counts = ['scheduled' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'not_yet' => 0];

            foreach ($employees as $employee) {
                if ($holidays->has($date) || $employee->join_date->toDateString() > $date) {
                    continue;
                }

                // Sama dengan Employee::scheduleOn(): penugasan terbaru yang berlaku pada tanggal itu.
                $schedule = $employee->schedules->first(fn ($s) => $s->effective_from->toDateString() <= $date
                    && ($s->effective_to === null || $s->effective_to->toDateString() >= $date))?->workSchedule;

                if ($schedule === null || ! $schedule->isWorkDay($day)) {
                    continue;
                }

                $counts['scheduled']++;
                $record = $records->get($employee->id.'|'.$date);
                $onLeave = ($leaves->get($employee->id) ?? collect())
                    ->contains(fn ($l) => $l->start_date->toDateString() <= $date && $l->end_date->toDateString() >= $date);

                if ($record?->status->isPresent()) {
                    $counts['present']++;
                    $counts['late'] += $record->status === AttendanceStatus::Late ? 1 : 0;
                } elseif ($record?->status === AttendanceStatus::Absent) {
                    $counts['absent']++;
                } elseif ($record?->status === AttendanceStatus::Leave || $onLeave) {
                    $counts['leave']++;
                } else {
                    $counts['not_yet']++;
                }
            }

            $expected = $counts['scheduled'] - $counts['leave'];
            $result[] = [
                'date' => $date,
                ...$counts,
                'rate' => $expected > 0 ? round($counts['present'] / $expected * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Karyawan aktif per departemen (urut terbanyak).
     *
     * @param  list<int>|null  $scope
     * @return list<array{name: string, total: int}>
     */
    public function departmentDistribution(?array $scope = null): array
    {
        $counts = $this->employees($scope)->selectRaw('department_id, COUNT(*) as total')->groupBy('department_id')->pluck('total', 'department_id');
        $names = Department::query()->whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts->map(fn ($total, $id) => ['name' => $names[$id] ?? 'Tanpa departemen', 'total' => (int) $total])
            ->sortByDesc('total')->values()->all();
    }

    /**
     * @param  list<int>|null  $scope
     * @return list<array{name: string, total: int}>
     */
    public function employmentStatusDistribution(?array $scope = null): array
    {
        $counts = $this->employees($scope)->selectRaw('employment_status, COUNT(*) as total')->groupBy('employment_status')->pluck('total', 'employment_status');

        return collect(EmploymentStatus::cases())
            ->map(fn (EmploymentStatus $s) => ['name' => $s->label(), 'total' => (int) ($counts[$s->value] ?? 0)])
            ->values()->all();
    }

    /**
     * Pengajuan berstatus Menunggu dalam cakupan.
     *
     * @param  list<int>|null  $scope
     * @return array{leave: int, overtime: int}
     */
    public function pendingRequests(?array $scope = null): array
    {
        $count = fn (string $class) => $class::query()
            ->where('status', RequestStatus::Pending->value)
            ->when($scope !== null, fn ($q) => $q->whereIn('employee_id', $scope ?: [0]))
            ->count();

        return ['leave' => $count(LeaveRequest::class), 'overtime' => $count(OvertimeRequest::class)];
    }

    /**
     * Pengajuan yang menunggu keputusan pengguna ini, untuk tabel "Menunggu Persetujuan".
     *
     * @return list<array<string, mixed>>
     */
    public function awaitingApprovals(User $user, int $limit = 5): array
    {
        $leave = $this->approvals->awaiting($user, LeaveRequest::class, LeaveService::PERMISSION)
            ->with(['employee:id,full_name', 'leaveType:id,name'])->latest('id')->limit($limit)->get()
            ->map(fn (LeaveRequest $r) => [
                'kind' => 'leave', 'id' => $r->id, 'employee' => $r->employee?->full_name,
                'type' => $r->leaveType?->name, 'detail' => "{$r->days} hari kerja",
                'date' => $r->start_date->toDateString(), 'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $overtime = $this->approvals->awaiting($user, OvertimeRequest::class, OvertimeService::PERMISSION)
            ->with(['employee:id,full_name'])->latest('id')->limit($limit)->get()
            ->map(fn (OvertimeRequest $r) => [
                'kind' => 'overtime', 'id' => $r->id, 'employee' => $r->employee?->full_name,
                'type' => 'Lembur', 'detail' => "{$r->duration_minutes} menit",
                'date' => $r->work_date->toDateString(), 'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return $leave->concat($overtime)->sortByDesc('created_at')->take($limit)->values()->all();
    }

    /**
     * Jumlah hari cuti disetujui (berdasarkan bulan mulai) & menit lembur disetujui per bulan, 6 bulan terakhir.
     *
     * @param  list<int>|null  $scope
     * @return list<array{month: string, leave_days: int, overtime_hours: float}>
     */
    public function leaveOvertimeTrend(int $months = 6, ?array $scope = null): array
    {
        $start = $this->today()->startOfMonth()->subMonths($months - 1);
        $scoped = fn ($q) => $q->when($scope !== null, fn ($w) => $w->whereIn('employee_id', $scope ?: [0]));

        $leave = LeaveRequest::query()->where('status', RequestStatus::Approved->value)
            ->whereDate('start_date', '>=', $start->toDateString())->tap($scoped)
            ->get(['start_date', 'days'])->groupBy(fn ($r) => $r->start_date->format('Y-m'));

        $overtime = OvertimeRequest::query()->where('status', RequestStatus::Approved->value)
            ->whereDate('work_date', '>=', $start->toDateString())->tap($scoped)
            ->get(['work_date', 'duration_minutes'])->groupBy(fn ($r) => $r->work_date->format('Y-m'));

        return collect(range(0, $months - 1))->map(function ($i) use ($start, $leave, $overtime) {
            $month = $start->addMonths($i)->format('Y-m');

            return [
                'month' => $month,
                'leave_days' => (int) ($leave->get($month)?->sum('days') ?? 0),
                'overtime_hours' => round(($overtime->get($month)?->sum('duration_minutes') ?? 0) / 60, 1),
            ];
        })->all();
    }

    /**
     * Ringkasan payroll periode terbaru.
     *
     * @return array{id: int, period: string, code: string, status: string, status_label: string, net_total: string, records: int, paid: int}|null
     */
    public function payrollSummary(): ?array
    {
        $period = PayrollPeriod::query()->latest('start_date')->first();

        if ($period === null) {
            return null;
        }

        $recordIds = $period->records()->pluck('id');

        return [
            'id' => $period->id,
            'period' => $period->label(),
            'code' => $period->code,
            'status' => $period->status->value,
            'status_label' => $period->status->label(),
            'net_total' => Money::of($period->records()->sum('net_pay')),
            'records' => $recordIds->count(),
            'paid' => Payment::query()->whereIn('payroll_record_id', $recordIds)->where('status', PaymentStatus::Paid->value)->count(),
        ];
    }

    /**
     * @return array{open_vacancies: int, applicants_this_month: int, stages: list<array{name: string, total: int}>}
     */
    public function recruitmentSummary(): array
    {
        $counts = JobApplication::query()
            ->whereHas('vacancy', fn ($q) => $q->where('status', '!=', 'closed'))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'open_vacancies' => JobVacancy::query()->open()->count(),
            'applicants_this_month' => JobApplication::query()->where('created_at', '>=', $this->today()->startOfMonth()->utc())->count(),
            'stages' => collect([ApplicationStatus::Applied, ApplicationStatus::Shortlisted, ApplicationStatus::Interview, ApplicationStatus::Accepted, ApplicationStatus::Hired])
                ->map(fn (ApplicationStatus $s) => ['name' => $s->label(), 'total' => (int) ($counts[$s->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Progres periode penilaian aktif & rata-rata nilai yang sudah dipublikasikan.
     *
     * @param  list<int>|null  $scope
     * @return array{period: string, total: int, completed: int, published: int, average: float|null}|null
     */
    public function performanceSummary(?array $scope = null): ?array
    {
        $period = PerformancePeriod::query()->where('status', 'active')->latest('start_date')->first()
            ?? PerformancePeriod::query()->latest('start_date')->first();

        if ($period === null) {
            return null;
        }

        $reviews = PerformanceReview::query()->where('performance_period_id', $period->id)
            ->when($scope !== null, fn ($q) => $q->whereIn('employee_id', $scope ?: [0]))
            ->get(['status', 'final_score']);
        $published = $reviews->where('status', ReviewStatus::Published);

        return [
            'period' => $period->name,
            'total' => $reviews->count(),
            'completed' => $reviews->whereIn('status', [ReviewStatus::Final, ReviewStatus::Published])->count(),
            'published' => $published->count(),
            'average' => $published->isEmpty() ? null : round((float) $published->avg('final_score'), 1),
        ];
    }

    /**
     * Aktivitas terbaru yang mudah dibaca, dari beberapa modul.
     *
     * @param  list<int>|null  $scope
     * @return list<array{actor: string, text: string, at: string|null}>
     */
    public function recentActivity(?array $scope = null, int $limit = 6): array
    {
        $scoped = fn ($q) => $q->when($scope !== null, fn ($w) => $w->whereIn('employee_id', $scope ?: [0]));

        $items = collect()
            ->concat(LeaveRequest::query()->tap($scoped)->with(['employee:id,full_name', 'leaveType:id,name'])->latest('id')->limit($limit)->get()
                ->map(fn ($r) => ['actor' => $r->employee?->full_name ?? '—', 'text' => "mengajukan {$r->leaveType?->name} ({$r->days} hari)", 'at' => $r->created_at]))
            ->concat(OvertimeRequest::query()->tap($scoped)->with('employee:id,full_name')->latest('id')->limit($limit)->get()
                ->map(fn ($r) => ['actor' => $r->employee?->full_name ?? '—', 'text' => "mengajukan lembur {$r->duration_minutes} menit", 'at' => $r->created_at]))
            ->concat($scope === null ? JobApplication::query()->with(['applicant:id,full_name', 'vacancy:id,title'])->latest('id')->limit($limit)->get()
                ->map(fn ($a) => ['actor' => $a->applicant?->full_name ?? '—', 'text' => "melamar {$a->vacancy?->title}", 'at' => $a->created_at]) : [])
            ->concat(Employee::query()->when($scope !== null, fn ($q) => $q->whereIn('id', $scope ?: [0]))->latest('id')->limit($limit)->get(['full_name', 'created_at'])
                ->map(fn ($e) => ['actor' => $e->full_name, 'text' => 'bergabung sebagai karyawan', 'at' => $e->created_at]));

        return $items->sortByDesc('at')->take($limit)
            ->map(fn ($i) => [...$i, 'at' => $i['at']?->toIso8601String()])
            ->values()->all();
    }

    // ---------- Karyawan (dashboard pribadi) ----------

    /** @return array<string, mixed> */
    public function personal(Employee $employee): array
    {
        $today = $this->today();
        $date = $today->toDateString();
        $tz = config('app.display_timezone');
        $record = AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->first();
        $schedule = $employee->scheduleOn($date);

        $annual = LeaveType::query()->where('uses_balance', true)->where('is_active', true)->orderBy('id')->first();
        $balance = null;

        if ($annual) {
            $b = $this->leaves->balanceFor($employee, $annual, (int) $today->year);
            $balance = [
                'type' => $annual->name,
                'quota' => $b->quota,
                'remaining' => max(0, $b->remaining() - $this->leaves->pendingDays($employee, $annual, (int) $today->year)),
            ];
        }

        // Hanya slip dari periode yang sudah disetujui (sama dengan aturan Slip Gaji Saya).
        $slip = PayrollRecord::query()
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($q) => $q->whereIn('status', ['approved', 'closed']))
            ->with('period')->get()->sortByDesc(fn ($r) => $r->period->start_date)->first();

        $review = PerformanceReview::query()->where('employee_id', $employee->id)
            ->where('status', ReviewStatus::Published->value)->with('period')->latest('published_at')->first();

        return [
            'attendance' => [
                'scheduled' => $schedule !== null && $schedule->isWorkDay($today) && ! Holiday::isHoliday($date),
                'schedule' => $schedule?->label(),
                'status' => $record?->status->value,
                'status_label' => $record?->status->label(),
                'check_in' => $record?->check_in_at?->setTimezone($tz)->format('H:i'),
                'check_out' => $record?->check_out_at?->setTimezone($tz)->format('H:i'),
                'on_leave' => $this->leaveCalendar->isOnApprovedLeave($employee, $date),
            ],
            'leave_balance' => $balance,
            'overtime_minutes_this_month' => (int) OvertimeRequest::query()->where('employee_id', $employee->id)
                ->where('status', RequestStatus::Approved->value)
                ->whereDate('work_date', '>=', $today->startOfMonth()->toDateString())->sum('duration_minutes'),
            'pending' => $this->pendingRequests([$employee->id]),
            'latest_slip' => $slip ? ['id' => $slip->id, 'period' => $slip->period->label(), 'net_pay' => $slip->net_pay] : null,
            'latest_review' => $review ? ['id' => $review->id, 'period' => $review->period->name, 'score' => $review->final_score, 'rating' => $review->rating] : null,
            'month_attendance' => AttendanceRecord::query()->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$today->startOfMonth()->toDateString(), $date])
                ->get(['status'])->countBy(fn ($r) => $r->status->value)->all(),
        ];
    }

    // ---------- Administrator ----------

    /** @return array<string, mixed> */
    public function admin(): array
    {
        $startOfDay = $this->today()->utc();
        $year = (int) $this->today()->year;

        return [
            'users' => User::query()->count(),
            'users_without_role' => User::query()->doesntHave('roles')->count(),
            'departments' => Department::query()->active()->count(),
            'positions' => Position::query()->where('is_active', true)->count(),
            'audit_today' => AuditLog::query()->where('created_at', '>=', $startOfDay)->count(),
            'roles' => RoleModel::query()->withCount('users')->orderBy('id')->get()
                ->map(fn ($r) => ['name' => $r->name, 'total' => $r->users_count])->all(),
            // Status konfigurasi: hal yang perlu disiapkan sebelum sistem dipakai penuh.
            'config' => [
                ['label' => 'Nama perusahaan diisi', 'ok' => ! in_array(SystemSetting::get('company_name'), [null, '', 'PT Contoh Indonesia'], true)],
                ['label' => 'Jadwal kerja aktif tersedia', 'ok' => WorkSchedule::query()->where('is_active', true)->exists()],
                ['label' => "Hari libur {$year} terisi", 'ok' => Holiday::query()->whereYear('date', $year)->count() >= 5],
                // Tarif boleh diisi di komponen (default) atau di struktur gaji karyawan.
                ['label' => 'Tarif BPJS/PPh diisi (komponen atau struktur gaji)', 'ok' => SalaryComponent::query()->whereIn('code', ['BPJS-KES', 'BPJS-JHT', 'BPJS-JP', 'PPH21'])
                    ->where(fn ($q) => $q->whereNotNull('default_rate')->orWhereNotNull('default_amount')
                        ->orWhereHas('structureItems', fn ($i) => $i->whereNotNull('rate')->orWhereNotNull('amount')))
                    ->exists()],
                ['label' => 'Pengiriman email (SMTP) dikonfigurasi', 'ok' => config('mail.default') !== 'log'],
            ],
            'audit' => AuditLog::query()->with('user:id,name')->latest('id')->limit(8)->get()->map(fn (AuditLog $l) => [
                'actor' => $l->user?->name ?? 'Sistem',
                'event' => ['created' => 'membuat', 'updated' => 'mengubah', 'deleted' => 'menghapus'][$l->event] ?? $l->event,
                'model' => class_basename($l->auditable_type),
                'at' => $l->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
