<?php

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00', 'Asia/Jakarta'));

    $this->schedule = WorkSchedule::create([
        'name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00',
        'work_days' => [1, 2, 3, 4, 5], 'late_tolerance_minutes' => 10,
    ]);
    $this->manager = Employee::factory()->withRole(Role::Manager)->create(['full_name' => 'Manajer']);
    $this->member = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create(['full_name' => 'Anggota Tim']);
    $this->outsider = Employee::factory()->withRole(Role::Karyawan)->create(['full_name' => 'Orang Luar']);
    $this->hrd = User::factory()->withRole(Role::Hrd)->create();

    foreach ([$this->member, $this->outsider] as $e) {
        AttendanceRecord::create([
            'employee_id' => $e->id, 'work_date' => '2026-10-07', 'status' => 'present',
            'check_in_at' => CarbonImmutable::parse('2026-10-07 08:00', 'Asia/Jakarta'),
        ]);
    }
});

test('HRD melihat rekap harian semua karyawan beserta ringkasan', function () {
    $this->actingAs($this->hrd)->get('/absensi?date=2026-10-07')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/index')
            ->has('daily.records.data', 2)
            ->where('daily.summary.present', 2)
            ->where('daily.records.data.0.check_in', '08:00')
            ->where('can.correct', true));
});

test('Manager hanya melihat absensi timnya dan tidak bisa mengoreksi', function () {
    $this->actingAs($this->manager->user)->get('/absensi?date=2026-10-07')
        ->assertInertia(fn (Assert $page) => $page
            ->has('daily.records.data', 1)
            ->where('daily.records.data.0.full_name', 'Anggota Tim')
            ->where('can.correct', false)
            ->where('can.export', false));

    $this->post('/absensi/koreksi', [
        'employee_id' => $this->member->id, 'work_date' => '2026-10-07', 'status' => 'absent', 'reason' => 'Coba koreksi',
    ])->assertForbidden();
});

test('Karyawan diarahkan ke Absensi Saya dan hanya melihat catatannya sendiri', function () {
    $this->actingAs($this->member->user)->get('/absensi')->assertRedirect('/absensi/saya');

    $this->get('/absensi/saya?month=2026-10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/mine')
            ->has('records', 1)
            ->where('records.0.employee_id', $this->member->id)
            ->where('summary.present', 1));

    $this->get('/absensi/ekspor')->assertForbidden();
});

test('rekap bulanan menghitung status per karyawan', function () {
    AttendanceRecord::create(['employee_id' => $this->member->id, 'work_date' => '2026-10-06', 'status' => 'late', 'late_minutes' => 25]);
    AttendanceRecord::create(['employee_id' => $this->member->id, 'work_date' => '2026-10-05', 'status' => 'absent']);

    $this->actingAs($this->hrd)->get('/absensi?mode=monthly&month=2026-10&search=Anggota')
        ->assertInertia(fn (Assert $page) => $page
            ->has('monthly.rows.data', 1)
            ->where('monthly.rows.data.0.present', 1)
            ->where('monthly.rows.data.0.late', 1)
            ->where('monthly.rows.data.0.absent', 1)
            ->where('monthly.rows.data.0.late_minutes', 25));
});

test('koreksi HRD wajib beralasan dan menyimpan nilai lama serta baru', function () {
    EmployeeSchedule::create(['employee_id' => $this->member->id, 'work_schedule_id' => $this->schedule->id, 'effective_from' => '2026-01-01']);
    $this->actingAs($this->hrd);

    $this->post('/absensi/koreksi', [
        'employee_id' => $this->member->id, 'work_date' => '2026-10-07', 'status' => 'late', 'check_in_time' => '08:30',
    ])->assertSessionHasErrors(['reason' => 'Alasan koreksi wajib diisi.']);

    $this->post('/absensi/koreksi', [
        'employee_id' => $this->member->id, 'work_date' => '2026-10-07', 'status' => 'late',
        'check_in_time' => '08:30', 'check_out_time' => '17:15', 'reason' => 'Kamera kios rusak',
    ])->assertSessionHas('success');

    $record = AttendanceRecord::query()->where('employee_id', $this->member->id)->sole();
    $correction = AttendanceCorrection::query()->sole();

    expect($record->status)->toBe(AttendanceStatus::Late)
        ->and($record->late_minutes)->toBe(30)
        ->and($record->source)->toBe('correction')
        ->and($correction->reason)->toBe('Kamera kios rusak')
        ->and($correction->user_id)->toBe($this->hrd->id)
        ->and($correction->old_values)->toMatchArray(['Masuk' => '08:00', 'Status' => 'Hadir'])
        ->and($correction->new_values)->toMatchArray(['Masuk' => '08:30', 'Pulang' => '17:15', 'Status' => 'Terlambat']);
});

test('koreksi menolak jam pulang sebelum jam masuk dan tanggal di masa depan', function () {
    $this->actingAs($this->hrd)->post('/absensi/koreksi', [
        'employee_id' => $this->member->id, 'work_date' => '2026-10-07', 'status' => 'present',
        'check_in_time' => '09:00', 'check_out_time' => '08:00', 'reason' => 'Uji validasi',
    ])->assertSessionHasErrors(['check_out_time' => 'Jam pulang harus setelah jam masuk.']);

    $this->post('/absensi/koreksi', [
        'employee_id' => $this->member->id, 'work_date' => '2026-12-01', 'status' => 'absent', 'reason' => 'Uji validasi',
    ])->assertSessionHasErrors('work_date');
});

test('HRD bisa mengekspor rekap harian dan bulanan', function () {
    $this->actingAs($this->hrd);

    expect($this->get('/absensi/ekspor?date=2026-10-07')->assertOk()->headers->get('content-disposition'))->toContain('absensi-2026-10-07.xlsx')
        ->and($this->get('/absensi/ekspor?mode=monthly&month=2026-10')->assertOk()->headers->get('content-disposition'))->toContain('absensi-2026-10.xlsx');
});

test('jadwal kerja: validasi, terapkan ke departemen, dan tidak bisa dihapus jika dipakai', function () {
    $this->actingAs($this->hrd);

    $this->post('/pengaturan/jadwal-kerja', ['name' => 'Shift', 'start_time' => '17:00', 'end_time' => '08:00', 'work_days' => [], 'check_in_opens_minutes' => 60, 'check_out_closes_minutes' => 60])
        ->assertSessionHasErrors([
            'end_time' => 'Jam pulang harus setelah jam masuk (shift lintas tengah malam belum didukung).',
            'work_days' => 'Pilih minimal satu hari kerja.',
        ]);

    $this->post('/pengaturan/jadwal-kerja', ['name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'work_days' => [1, 2, 3, 4, 5, 6], 'check_in_opens_minutes' => 60, 'check_out_closes_minutes' => 120])
        ->assertSessionHasNoErrors();
    $pagi = WorkSchedule::query()->where('name', 'Pagi')->sole();

    $department = Department::factory()->create();
    $a = Employee::factory()->create(['department_id' => $department->id]);
    $b = Employee::factory()->create(['department_id' => $department->id]);

    $this->post("/pengaturan/jadwal-kerja/{$pagi->id}/terapkan", ['department_id' => $department->id])
        ->assertSessionHas('success', 'Jadwal Pagi diterapkan ke 2 karyawan mulai hari ini.');

    expect($a->scheduleOn('2026-10-07')?->id)->toBe($pagi->id)
        ->and($b->scheduleOn('2026-10-07')?->id)->toBe($pagi->id);

    $this->delete("/pengaturan/jadwal-kerja/{$pagi->id}")->assertSessionHas('error');
    $this->assertDatabaseHas('work_schedules', ['id' => $pagi->id]);
});

test('hari libur tidak boleh ganda dan hanya dikelola HRD', function () {
    $this->actingAs($this->hrd)->post('/pengaturan/hari-libur', ['date' => '2026-12-25', 'name' => 'Natal'])->assertSessionHasNoErrors();
    $this->post('/pengaturan/hari-libur', ['date' => '2026-12-25', 'name' => 'Natal lagi'])
        ->assertSessionHasErrors(['date' => 'Tanggal ini sudah terdaftar sebagai hari libur.']);

    $this->actingAs($this->manager->user)->get('/pengaturan/hari-libur')->assertForbidden();
});

test('mengganti jadwal karyawan menutup jadwal lama dan tercatat di riwayat', function () {
    $pagi = WorkSchedule::create(['name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'work_days' => [1, 2, 3, 4, 5]]);
    EmployeeSchedule::create(['employee_id' => $this->member->id, 'work_schedule_id' => $this->schedule->id, 'effective_from' => '2026-01-01']);

    $m = $this->member->load('user', 'bankAccount');
    $this->actingAs($this->hrd)->post("/karyawan/{$m->id}", [
        'nik' => $m->nik, 'full_name' => $m->full_name, 'email' => $m->user->email,
        'department_id' => $m->department_id, 'position_id' => $m->position_id,
        'manager_id' => $m->manager_id, 'employment_status' => 'permanent',
        'join_date' => $m->join_date->toDateString(), 'work_schedule_id' => $pagi->id,
    ])->assertSessionHasNoErrors();

    expect($m->scheduleOn('2026-10-07')->id)->toBe($pagi->id)
        ->and($m->scheduleOn('2026-10-06')->id)->toBe($this->schedule->id)
        ->and($m->histories()->where('type', 'updated')->first()->changes)->toHaveKey('Jadwal kerja');
});
