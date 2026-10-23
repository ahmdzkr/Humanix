<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveTypesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Manager → anggota tim; satu karyawan lain di luar tim. Bulan laporan: Oktober 2026.
*/
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00', 'Asia/Jakarta'));
    $this->seed(LeaveTypesSeeder::class);
    $this->hrd = User::factory()->withRole(Role::Hrd)->create();
    $this->manager = Employee::factory()->withRole(Role::Manager)->create(['full_name' => 'Manajer Satu']);
    $this->member = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create(['full_name' => 'Anggota Tim', 'nik' => 'EMP-1001']);
    $this->outsider = Employee::factory()->withRole(Role::Karyawan)->create(['full_name' => 'Orang Luar', 'nik' => 'EMP-2002']);

    $annual = LeaveType::query()->where('code', 'CT')->sole();

    foreach ([[$this->member, 2, 'approved'], [$this->member, 1, 'pending'], [$this->outsider, 3, 'approved'], [$this->outsider, 5, 'cancelled']] as [$e, $days, $status]) {
        LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07',
            'days' => $days, 'reason' => 'x', 'status' => $status]);
    }

    OvertimeRequest::create(['employee_id' => $this->member->id, 'work_date' => '2026-10-06',
        'start_at' => CarbonImmutable::parse('2026-10-06 17:00', 'Asia/Jakarta'), 'end_at' => CarbonImmutable::parse('2026-10-06 18:30', 'Asia/Jakarta'),
        'duration_minutes' => 90, 'reason' => 'x', 'status' => 'approved']);
});

test('HRD melihat laporan seluruh perusahaan; pengajuan dibatalkan tidak dihitung', function () {
    $this->actingAs($this->hrd)->get('/laporan?month=2026-10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('scopeLabel', 'Seluruh perusahaan')
            ->where('summary.leave_days', 5)       // 2 (tim) + 3 (luar); pending & batal tidak
            ->where('summary.overtime_hours', 1.5)
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'Orang Luar')['leave_requests'] === 1)
            ->whereNot('downloads.employees', null)
            ->whereNot('downloads.attendance', null));
});

test('Manager hanya melihat timnya dan tidak mendapat unduhan khusus HRD', function () {
    $this->actingAs($this->manager->user)->get('/laporan?month=2026-10')
        ->assertInertia(fn (Assert $page) => $page
            ->where('scopeLabel', 'Tim Anda')
            ->where('summary.headcount', 1)
            ->where('summary.leave_days', 2)
            ->has('rows', 1)
            ->where('rows.0.name', 'Anggota Tim')
            ->where('rows.0.leave_requests', 2)
            ->where('downloads.employees', null)
            ->where('downloads.attendance', null)
            ->where('payrollPeriods', []));

    $this->get('/laporan/cuti-lembur?month=2026-10')
        ->assertOk()
        ->assertDownload('rekap-cuti-lembur-2026-10.xlsx');
});

test('bulan tidak valid kembali ke bulan berjalan', function () {
    $this->actingAs($this->hrd)->get('/laporan?month=2026-13')
        ->assertInertia(fn (Assert $page) => $page->where('filters.month', '2026-10'));
});

test('pencarian header: halaman sesuai izin dan karyawan sesuai cakupan', function () {
    // HRD: menemukan siapa pun.
    $this->actingAs($this->hrd)->getJson('/cari?q=EMP-2002')
        ->assertOk()
        ->assertJsonPath('employees.0.title', 'Orang Luar')
        ->assertJsonPath('employees.0.url', "/karyawan/{$this->outsider->id}");

    // Manager: hanya anggota timnya.
    $this->actingAs($this->manager->user)->getJson('/cari?q=EMP-')
        ->assertJsonCount(1, 'employees')
        ->assertJsonPath('employees.0.title', 'Anggota Tim');

    // Karyawan: tidak mendapat data karyawan, hanya halaman yang boleh dibuka.
    $this->actingAs($this->member->user)->getJson('/cari?q=gaji')
        ->assertJsonPath('employees', [])
        ->assertJsonFragment(['title' => 'Slip Gaji Saya'])
        ->assertJsonMissing(['title' => 'Komponen Gaji']);

    $this->getJson('/cari?q=a')->assertExactJson(['pages' => [], 'employees' => []]);
});
