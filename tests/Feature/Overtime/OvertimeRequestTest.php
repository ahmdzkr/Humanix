<?php

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OvertimeRequest;
use App\Models\SystemSetting;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemSettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| "Hari ini" Rabu 7 Oktober 2026. Jadwal 08:00–17:00 Senin–Jumat. Batas lembur 240 menit/hari.
*/
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00', 'Asia/Jakarta'));
    $this->seed(SystemSettingsSeeder::class);

    $schedule = WorkSchedule::create(['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'work_days' => [1, 2, 3, 4, 5]]);
    $this->manager = Employee::factory()->withRole(Role::Manager)->create();
    $this->staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create();
    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;

    foreach ([$this->manager, $this->staff] as $e) {
        EmployeeSchedule::create(['employee_id' => $e->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01']);
    }
});

function submitOvertime(Employee $employee, array $overrides = [])
{
    return test()->actingAs($employee->user)->post('/lembur', array_merge([
        'work_date' => '2026-10-07',
        'start_time' => '17:00',
        'end_time' => '19:00',
        'reason' => 'Menyelesaikan laporan bulanan',
    ], $overrides));
}

test('pengajuan lembur menghitung durasi dan menyimpan jam dalam UTC', function () {
    submitOvertime($this->staff)->assertSessionHasNoErrors()->assertSessionHas('success');

    $overtime = OvertimeRequest::query()->sole();

    expect($overtime->duration_minutes)->toBe(120)
        ->and($overtime->status)->toBe(RequestStatus::Pending)
        ->and($overtime->payable)->toBeFalse()
        ->and($overtime->start_at->utc()->format('H:i'))->toBe('10:00')
        ->and($overtime->approvalSteps()->pluck('status')->all())->toBe(['pending', 'waiting']);
});

test('validasi: jam selesai setelah mulai, durasi minimal, dan tidak di dalam jam kerja', function () {
    submitOvertime($this->staff, ['start_time' => '19:00', 'end_time' => '18:00'])
        ->assertSessionHasErrors(['end_time' => 'Jam selesai harus setelah jam mulai (lembur lintas tengah malam belum didukung).']);

    submitOvertime($this->staff, ['start_time' => '17:00', 'end_time' => '17:20'])
        ->assertSessionHasErrors(['end_time' => 'Durasi lembur minimal 30 menit.']);

    submitOvertime($this->staff, ['start_time' => '16:00', 'end_time' => '18:00'])
        ->assertSessionHasErrors(['start_time' => 'Lembur tidak boleh berada di dalam jam kerja (08:00–17:00).']);

    // Akhir pekan boleh di jam berapa pun.
    submitOvertime($this->staff, ['work_date' => '2026-10-10', 'start_time' => '09:00', 'end_time' => '12:00'])
        ->assertSessionHasNoErrors();

    submitOvertime($this->staff, ['work_date' => '2026-12-01'])->assertSessionHasErrors('work_date');
});

test('lembur yang tumpang tindih ditolak (duplikasi lembur)', function () {
    submitOvertime($this->staff)->assertSessionHasNoErrors();

    submitOvertime($this->staff, ['start_time' => '18:00', 'end_time' => '20:00'])
        ->assertSessionHasErrors(['start_time' => 'Waktu bertumpang tindih dengan pengajuan lembur lain (17:00–19:00).']);
    submitOvertime($this->staff)->assertSessionHasErrors('start_time');

    // Berdampingan (tanpa irisan) boleh.
    submitOvertime($this->staff, ['start_time' => '19:00', 'end_time' => '20:00'])->assertSessionHasNoErrors();

    expect(OvertimeRequest::query()->count())->toBe(2);
});

test('total lembur per hari tidak boleh melebihi batas di pengaturan', function () {
    submitOvertime($this->staff, ['start_time' => '17:00', 'end_time' => '20:00'])->assertSessionHasNoErrors();

    submitOvertime($this->staff, ['start_time' => '20:00', 'end_time' => '21:30'])
        ->assertSessionHasErrors(['end_time' => 'Total lembur pada tanggal ini menjadi 270 menit, melebihi batas 240 menit per hari.']);

    SystemSetting::set('max_minutes_per_day', 300);
    submitOvertime($this->staff, ['start_time' => '20:00', 'end_time' => '21:30'])->assertSessionHasNoErrors();
});

test('lembur yang ditolak atau dibatalkan tidak dihitung bentrok/batas', function () {
    submitOvertime($this->staff, ['start_time' => '17:00', 'end_time' => '21:00']);
    $first = OvertimeRequest::query()->sole();
    $this->actingAs($this->staff->user)->post("/lembur/{$first->id}/batal")->assertSessionHas('success');

    submitOvertime($this->staff, ['start_time' => '17:00', 'end_time' => '21:00'])->assertSessionHasNoErrors();
});

test('alur persetujuan atasan lalu HRD menandai lembur siap dibayar', function () {
    submitOvertime($this->staff);
    $overtime = OvertimeRequest::query()->sole();

    $this->actingAs($this->manager->user)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'approve', 'version' => 1])->assertSessionHas('success');
    expect($overtime->fresh()->payable)->toBeFalse();

    $this->actingAs($this->hrd)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'approve', 'version' => 2])->assertSessionHas('success');

    expect($overtime->fresh())
        ->status->toBe(RequestStatus::Approved)
        ->payable->toBeTrue();

    // Keputusan ulang dengan versi lama ditolak.
    $this->actingAs($this->hrd)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'reject', 'note' => 'x', 'version' => 2])
        ->assertSessionHasErrors('version');
});

test('lembur ditolak tidak dibayar dan akses horizontal ditolak', function () {
    submitOvertime($this->staff);
    $overtime = OvertimeRequest::query()->sole();

    $this->actingAs($this->manager->user)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'reject', 'note' => 'Tidak mendesak', 'version' => 1]);
    expect($overtime->fresh())->status->toBe(RequestStatus::Rejected)->payable->toBeFalse();

    $this->actingAs(Employee::factory()->withRole(Role::Karyawan)->create()->user)->get("/lembur/{$overtime->id}")->assertForbidden();
    $this->actingAs(Employee::factory()->withRole(Role::Manager)->create()->user)->get("/lembur/{$overtime->id}")->assertForbidden();
    $this->actingAs($this->staff->user)->get("/lembur/{$overtime->id}")->assertOk();
});

test('halaman Lembur menampilkan ringkasan sendiri dan antrean persetujuan atasan', function () {
    submitOvertime($this->staff);

    $this->actingAs($this->staff->user)->get('/lembur')
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/index')
            ->where('summary.pending_minutes', 120)
            ->where('maxMinutesPerDay', 240));

    $this->actingAs($this->manager->user)->get('/lembur?tab=approvals')
        ->assertInertia(fn (Assert $page) => $page->where('awaitingCount', 1)->has('requests.data', 1));
});

test('lembur HRD tanpa atasan diputuskan Manager mana pun, bukan HRD (termasuk dirinya sendiri)', function () {
    $hrdEmployee = $this->hrd->employee;
    $otherHrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;

    submitOvertime($hrdEmployee)->assertSessionHasNoErrors();
    $overtime = OvertimeRequest::query()->sole();

    expect($overtime->approvalSteps()->get(['approver_role', 'status'])->toArray())->toBe([['approver_role' => 'managers', 'status' => 'pending']]);

    // Masuk antrean semua Manager; HRD (pengaju maupun HRD lain) tidak bisa memutuskan.
    foreach ([$this->manager->user, $otherManager] as $manager) {
        $this->actingAs($manager)->get('/lembur?tab=approvals')->assertInertia(fn (Assert $page) => $page->where('awaitingCount', 1));
        $this->actingAs($manager)->get("/lembur/{$overtime->id}")->assertOk();
    }

    foreach ([$this->hrd, $otherHrd, $this->staff->user] as $user) {
        $response = $this->actingAs($user)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'approve', 'version' => 1]);
        expect($response->status())->toBe(403);
    }

    $this->actingAs($otherManager)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'approve', 'version' => 1])->assertSessionHas('success');

    expect($overtime->fresh())->status->toBe(RequestStatus::Approved)->payable->toBeTrue()
        ->and($overtime->approvalSteps()->sole()->decided_by)->toBe($otherManager->id);
    expect($this->hrd->notifications()->latest()->first()->data['title'])->toBe('Pengajuan Lembur disetujui');
});

test('lembur HRD dengan atasan berrole Manager diputuskan atasan itu saja, dan bisa ditolak', function () {
    $hrdEmployee = $this->hrd->employee;
    $hrdEmployee->update(['manager_id' => $this->manager->id]);
    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;

    submitOvertime($hrdEmployee->fresh())->assertSessionHasNoErrors();
    $overtime = OvertimeRequest::query()->sole();

    expect($overtime->approvalSteps()->sole())->approver_role->toBe('manager')->assigned_user_id->toBe($this->manager->user_id);

    $this->actingAs($otherManager)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'approve', 'version' => 1])->assertForbidden();
    $this->actingAs($this->manager->user)->post("/lembur/{$overtime->id}/keputusan", ['decision' => 'reject', 'note' => 'Tidak mendesak', 'version' => 1])
        ->assertSessionHas('success');

    expect($overtime->fresh())->status->toBe(RequestStatus::Rejected)->payable->toBeFalse();
});

test('lembur karyawan biasa tetap memakai alur Atasan lalu HRD', function () {
    submitOvertime($this->staff);

    expect(OvertimeRequest::query()->sole()->approvalSteps()->orderBy('step')->pluck('approver_role')->all())->toBe(['manager', 'hrd']);
});
