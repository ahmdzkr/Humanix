<?php

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DashboardMetrics;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Data yang diketahui — "hari ini" Rabu 7 Okt 2026, 09:00 WIB, jadwal Senin–Jumat.
| IT : A (Hadir), B (Terlambat), C (Tidak Hadir), D (cuti disetujui)
| HR : E (belum absen, punya cuti Menunggu), H (tanpa jadwal)
| Tidak dihitung: F (nonaktif), G (mulai kerja bulan depan)
|
| Ekspektasi: headcount 6; terjadwal 5; hadir 2 (1 terlambat); tidak hadir 1; cuti 1; belum absen 1;
| persentase hadir = 2 ÷ (5 − 1) = 50%. Distribusi: Teknologi 4, HR 2.
*/
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00', 'Asia/Jakarta'));
    $this->seed(LeaveTypesSeeder::class);

    $schedule = WorkSchedule::create(['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'work_days' => [1, 2, 3, 4, 5]]);
    $this->it = Department::factory()->create(['name' => 'Teknologi']);
    $this->hrDept = Department::factory()->create(['name' => 'HR']);
    $itPos = Position::factory()->create(['department_id' => $this->it->id]);
    $hrPos = Position::factory()->create(['department_id' => $this->hrDept->id]);

    $make = function (string $name, Department $dept, Position $pos, array $extra = [], bool $withSchedule = true) use ($schedule) {
        $e = Employee::factory()->create([...['full_name' => $name, 'department_id' => $dept->id, 'position_id' => $pos->id, 'join_date' => '2025-01-01'], ...$extra]);

        if ($withSchedule) {
            EmployeeSchedule::create(['employee_id' => $e->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2025-01-01']);
        }

        return $e;
    };

    $this->a = $make('A', $this->it, $itPos);
    $this->b = $make('B', $this->it, $itPos);
    $this->c = $make('C', $this->it, $itPos);
    $this->d = $make('D', $this->it, $itPos);
    $this->e = $make('E', $this->hrDept, $hrPos);
    $this->h = $make('H', $this->hrDept, $hrPos, [], false);
    $make('F', $this->it, $itPos, ['is_active' => false]);
    $make('G', $this->it, $itPos, ['join_date' => '2026-11-02']);

    $at = fn (string $t) => CarbonImmutable::parse("2026-10-07 {$t}", 'Asia/Jakarta');
    AttendanceRecord::create(['employee_id' => $this->a->id, 'work_date' => '2026-10-07', 'status' => 'present', 'check_in_at' => $at('07:55')]);
    AttendanceRecord::create(['employee_id' => $this->b->id, 'work_date' => '2026-10-07', 'status' => 'late', 'late_minutes' => 20, 'check_in_at' => $at('08:20')]);
    AttendanceRecord::create(['employee_id' => $this->c->id, 'work_date' => '2026-10-07', 'status' => 'absent']);

    $annual = LeaveType::query()->where('code', 'CT')->sole();
    LeaveRequest::create(['employee_id' => $this->d->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-10-07', 'end_date' => '2026-10-08', 'days' => 2, 'reason' => 'x', 'status' => 'approved']);
    LeaveRequest::create(['employee_id' => $this->e->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'days' => 1, 'reason' => 'x', 'status' => 'pending']);
    OvertimeRequest::create([
        'employee_id' => $this->a->id, 'work_date' => '2026-10-06',
        'start_at' => CarbonImmutable::parse('2026-10-06 17:00', 'Asia/Jakarta'), 'end_at' => CarbonImmutable::parse('2026-10-06 19:30', 'Asia/Jakarta'),
        'duration_minutes' => 150, 'reason' => 'x', 'status' => 'approved', 'payable' => true,
    ]);
    OvertimeRequest::create([
        'employee_id' => $this->b->id, 'work_date' => '2026-10-08',
        'start_at' => CarbonImmutable::parse('2026-10-08 17:00', 'Asia/Jakarta'), 'end_at' => CarbonImmutable::parse('2026-10-08 18:00', 'Asia/Jakarta'),
        'duration_minutes' => 60, 'reason' => 'x', 'status' => 'pending',
    ]);

    $this->metrics = app(DashboardMetrics::class);
});

test('headcount dan kehadiran hari ini sesuai definisi', function () {
    expect($this->metrics->headcount())->toBe(6)
        ->and($this->metrics->attendanceOn('2026-10-07'))->toMatchArray([
            'scheduled' => 5, 'present' => 2, 'late' => 1, 'absent' => 1, 'leave' => 1, 'not_yet' => 1, 'rate' => 50.0,
        ]);
});

test('persentase kehadiran aman terhadap nol (hari libur / akhir pekan)', function () {
    expect($this->metrics->attendanceOn('2026-10-10'))->toMatchArray(['scheduled' => 0, 'present' => 0, 'rate' => 0.0]);
});

test('distribusi departemen, status kepegawaian, pengajuan menunggu, dan tren cuti/lembur', function () {
    expect($this->metrics->departmentDistribution())->toBe([['name' => 'Teknologi', 'total' => 4], ['name' => 'HR', 'total' => 2]])
        ->and($this->metrics->pendingRequests())->toBe(['leave' => 1, 'overtime' => 1])
        ->and(collect($this->metrics->employmentStatusDistribution())->firstWhere('name', 'Tetap')['total'])->toBe(6);

    $trend = collect($this->metrics->leaveOvertimeTrend(6));
    expect($trend)->toHaveCount(6)
        ->and($trend->last())->toBe(['month' => '2026-10', 'leave_days' => 2, 'overtime_hours' => 2.5])
        ->and($trend->first()['month'])->toBe('2026-05');
});

test('tren kehadiran 7 hari berisi 7 tanggal dengan hari ini terakhir', function () {
    $trend = $this->metrics->attendanceTrend(7);

    expect($trend)->toHaveCount(7)
        ->and($trend[0]['date'])->toBe('2026-10-01')
        ->and($trend[6]['date'])->toBe('2026-10-07')
        ->and($trend[6]['present'])->toBe(2)
        ->and($trend[2]['scheduled'])->toBe(0); // Sabtu 3 Okt
});

test('dashboard HRD menampilkan angka yang sama dan filter departemen membatasi cakupan', function () {
    $hrd = User::factory()->withRole(Role::Hrd)->create();

    $this->actingAs($hrd)->get('/hr/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/hr')
            ->where('kpi.headcount', 6)
            ->where('kpi.today.present', 2)
            ->where('kpi.today.rate', 50)
            ->where('kpi.pending_leave', 1)
            ->where('kpi.pending_overtime', 1)
            ->has('attendanceTrend', 7)
            ->where('departments.0.total', 4));

    $this->get("/hr/dashboard?department={$this->hrDept->id}&days=30")
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpi.headcount', 2)
            ->where('kpi.today.scheduled', 1)
            ->where('kpi.today.not_yet', 1)
            ->where('kpi.pending_leave', 1)
            ->where('kpi.pending_overtime', 0)
            ->has('attendanceTrend', 30)
            ->where('departments', [['name' => 'HR', 'total' => 2]]));
});

test('dashboard Manager hanya menghitung timnya', function () {
    $manager = Employee::factory()->withRole(Role::Manager)->create(['join_date' => '2025-01-01']);
    $this->a->update(['manager_id' => $manager->id]);
    $this->c->update(['manager_id' => $manager->id]);

    $this->actingAs($manager->user)->get('/manager/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/manager')
            ->where('kpi.team', 2)
            ->where('kpi.today.present', 1)
            ->where('kpi.today.absent', 1)
            ->where('kpi.overtime_hours', 2.5));
});

test('dashboard Karyawan menampilkan absensi hari ini, saldo cuti, dan lembur miliknya saja', function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->b->user->syncRoles([Role::Karyawan->value]);

    $this->actingAs($this->b->user)->get('/me/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/employee')
            ->where('personal.attendance.status', 'late')
            ->where('personal.attendance.check_in', '08:20')
            ->where('personal.leave_balance.remaining', 12)
            ->where('personal.overtime_minutes_this_month', 0)
            ->where('personal.pending.overtime', 1)
            ->where('personal.latest_slip', null));
});

test('dashboard Administrator menampilkan akun, distribusi role, dan status konfigurasi', function () {
    $admin = User::factory()->withRole(Role::Administrator)->create();

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/admin')
            ->where('metrics.users', User::query()->count())
            ->where('metrics.roles', fn ($roles) => collect($roles)->firstWhere('name', 'Administrator')['total'] === 1)
            ->has('metrics.config', 5));
});

test('tren kehadiran memuat data sekali: jumlah query tidak bertambah per hari', function () {
    $count = function (int $days) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->metrics->attendanceTrend($days);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($count(30))->toBe($count(7));
});
