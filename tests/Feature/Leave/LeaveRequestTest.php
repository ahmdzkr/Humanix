<?php

use App\Enums\AttendanceStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveTypesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Struktur: manager (role Manager) → staff (role Karyawan). HRD terpisah.
| "Hari ini" = Rabu 7 Oktober 2026. Jadwal Senin–Jumat.
*/
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00', 'Asia/Jakarta'));
    $this->seed(LeaveTypesSeeder::class);

    $this->annual = LeaveType::query()->where('code', 'CT')->sole();
    $this->sick = LeaveType::query()->where('code', 'SKT')->sole();
    $this->permit = LeaveType::query()->where('code', 'IZN')->sole();

    $schedule = WorkSchedule::create(['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'work_days' => [1, 2, 3, 4, 5]]);
    $this->manager = Employee::factory()->withRole(Role::Manager)->create();
    $this->staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create();
    $this->hrdEmployee = Employee::factory()->withRole(Role::Hrd)->create();
    $this->hrd = $this->hrdEmployee->user;

    foreach ([$this->manager, $this->staff, $this->hrdEmployee] as $e) {
        EmployeeSchedule::create(['employee_id' => $e->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01']);
    }
});

function submitLeave(Employee $employee, array $overrides = [])
{
    return test()->actingAs($employee->user)->post('/cuti-izin', array_merge([
        'leave_type_id' => test()->annual->id,
        'start_date' => '2026-10-12', // Senin
        'end_date' => '2026-10-16',   // Jumat
        'reason' => 'Liburan keluarga',
    ], $overrides));
}

function decideLeave(User $user, LeaveRequest $leave, string $decision, ?string $note = null, ?int $version = null)
{
    return test()->actingAs($user)->post("/cuti-izin/{$leave->id}/keputusan", [
        'decision' => $decision,
        'note' => $note,
        'version' => $version ?? $leave->fresh()->version,
    ]);
}

test('jumlah hari dihitung dari hari kerja: tanpa akhir pekan dan hari libur', function () {
    Holiday::create(['date' => '2026-10-14', 'name' => 'Libur']);

    submitLeave($this->staff, ['start_date' => '2026-10-09', 'end_date' => '2026-10-16'])->assertSessionHasNoErrors();

    // Jum 9, Sen 12, Sel 13, (Rab 14 libur), Kam 15, Jum 16 = 5 hari.
    expect(LeaveRequest::query()->sole()->days)->toBe(5);
});

test('pengajuan membuat langkah Atasan (berjalan) lalu HRD (menunggu giliran)', function () {
    submitLeave($this->staff)->assertRedirect()->assertSessionHas('success');

    $leave = LeaveRequest::query()->sole();
    $steps = $leave->approvalSteps()->get();

    expect($leave->status)->toBe(RequestStatus::Pending)
        ->and($steps->pluck('approver_role')->all())->toBe(['manager', 'hrd'])
        ->and($steps->pluck('status')->all())->toBe(['pending', 'waiting'])
        ->and($steps[0]->assigned_user_id)->toBe($this->manager->user_id);
});

test('tanpa atasan, langkah atasan dilewati dan langsung ke HRD', function () {
    submitLeave($this->manager)->assertSessionHasNoErrors();

    expect(LeaveRequest::query()->sole()->approvalSteps()->pluck('status')->all())->toBe(['skipped', 'pending']);
});

test('cuti HRD tanpa atasan diputuskan Manager mana pun (bukan HRD); saldo terpotong sekali', function () {
    $otherHrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;

    submitLeave($this->hrdEmployee, ['start_date' => '2026-10-12', 'end_date' => '2026-10-13'])->assertSessionHasNoErrors();
    $leave = LeaveRequest::query()->sole();

    expect($leave->approvalSteps()->get(['approver_role', 'status'])->toArray())->toBe([['approver_role' => 'managers', 'status' => 'pending']]);

    foreach ([$this->manager->user, $otherManager] as $manager) {
        $this->actingAs($manager)->get('/cuti-izin?tab=approvals')->assertInertia(fn (Assert $page) => $page->where('awaitingCount', 1));
        $this->actingAs($manager)->get("/cuti-izin/{$leave->id}")->assertOk();
    }

    foreach ([$this->hrd, $otherHrd, $this->staff->user] as $user) {
        expect(decideLeave($user, $leave, 'approve')->status())->toBe(403);
    }

    decideLeave($otherManager, $leave, 'approve')->assertSessionHas('success');
    // Keputusan kedua (Manager lain, versi lama) ditolak; saldo tidak terpotong dua kali.
    decideLeave($this->manager->user, $leave, 'approve', null, 1)->assertSessionHasErrors('version');

    expect($leave->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($leave->approvalSteps()->sole()->decided_by)->toBe($otherManager->id)
        ->and(LeaveBalance::query()->where('employee_id', $this->hrdEmployee->id)->sole()->used)->toBe(2.0)
        ->and($this->hrd->notifications()->latest()->first()->data['title'])->toBe('Pengajuan Cuti/Izin disetujui');
});

test('cuti HRD dengan atasan berrole Manager diputuskan atasan itu saja; penolakan tidak memotong saldo', function () {
    $this->hrdEmployee->update(['manager_id' => $this->manager->id]);
    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;

    submitLeave($this->hrdEmployee->fresh())->assertSessionHasNoErrors();
    $leave = LeaveRequest::query()->sole();

    expect($leave->approvalSteps()->sole())->approver_role->toBe('manager')->assigned_user_id->toBe($this->manager->user_id);

    expect(decideLeave($otherManager, $leave, 'approve')->status())->toBe(403);
    decideLeave($this->manager->user, $leave, 'reject', 'Periode tutup buku')->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and(LeaveBalance::query()->where('employee_id', $this->hrdEmployee->id)->value('used'))->toEqual(0);
});

test('alur lengkap: atasan setuju, HRD setuju, saldo berkurang, absensi Tidak Hadir menjadi Cuti', function () {
    AttendanceRecord::create(['employee_id' => $this->staff->id, 'work_date' => '2026-10-05', 'status' => 'absent', 'source' => 'system']);
    submitLeave($this->staff, ['start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->assertSessionHasNoErrors();
    $leave = LeaveRequest::query()->sole();

    decideLeave($this->manager->user, $leave, 'approve')->assertSessionHas('success');
    expect($leave->fresh()->status)->toBe(RequestStatus::Pending)
        ->and($leave->approvalSteps()->pluck('status')->all())->toBe(['approved', 'pending']);

    decideLeave($this->hrd, $leave, 'approve', 'Selamat berlibur')->assertSessionHas('success');

    $balance = LeaveBalance::query()->where('employee_id', $this->staff->id)->sole();
    expect($leave->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($leave->fresh()->version)->toBe(3)
        ->and($balance->used)->toBe(2.0)
        ->and($balance->quota)->toBe(12.0)
        ->and(AttendanceRecord::query()->where('work_date', '2026-10-05')->sole()->status)->toBe(AttendanceStatus::Leave);
});

test('penolakan wajib beralasan, menghentikan alur, dan tidak memotong saldo', function () {
    submitLeave($this->staff);
    $leave = LeaveRequest::query()->sole();

    decideLeave($this->manager->user, $leave, 'reject')->assertSessionHasErrors(['note' => 'Alasan penolakan wajib diisi.']);
    decideLeave($this->manager->user, $leave, 'reject', 'Sedang periode audit')->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($leave->approvalSteps()->pluck('status')->all())->toBe(['rejected', 'cancelled'])
        ->and(LeaveBalance::query()->where('employee_id', $this->staff->id)->value('used'))->toEqual(0);
});

test('keputusan dengan versi lama ditolak sehingga pengajuan tidak diproses ganda', function () {
    submitLeave($this->manager); // langsung ke langkah HRD
    $leave = LeaveRequest::query()->sole();
    $staleVersion = $leave->version;
    $otherHrd = User::factory()->withRole(Role::Hrd)->create();

    decideLeave($this->hrd, $leave, 'approve', null, $staleVersion)->assertSessionHas('success');
    decideLeave($otherHrd, $leave, 'approve', null, $staleVersion)
        ->assertSessionHasErrors(['version' => 'Pengajuan ini sudah diproses atau berubah. Muat ulang halaman untuk melihat status terbaru.']);

    expect(LeaveBalance::query()->where('employee_id', $this->manager->id)->value('used'))->toEqual(5);
});

test('tidak ada yang bisa menyetujui pengajuannya sendiri, dan hanya atasan yang ditugaskan yang bisa memproses', function () {
    submitLeave($this->hrdEmployee); // HRD tanpa atasan → diputuskan Manager
    $own = LeaveRequest::query()->sole();
    decideLeave($this->hrd, $own, 'approve')->assertForbidden();

    $otherHrd = User::factory()->withRole(Role::Hrd)->create();
    decideLeave($otherHrd, $own, 'approve')->assertForbidden();
    decideLeave($this->manager->user, $own, 'approve')->assertSessionHas('success');

    submitLeave($this->staff, ['start_date' => '2026-11-02', 'end_date' => '2026-11-02']);
    $staffLeave = LeaveRequest::query()->where('employee_id', $this->staff->id)->sole();
    $otherManager = Employee::factory()->withRole(Role::Manager)->create();

    decideLeave($otherManager->user, $staffLeave, 'approve')->assertForbidden();
    decideLeave($this->hrd, $staffLeave, 'approve')->assertForbidden(); // belum giliran HRD
    decideLeave($this->staff->user, $staffLeave, 'approve')->assertForbidden();
});

test('atasan langsung berrole Karyawan (supervisor) tetap bisa menyetujui bawahannya', function () {
    $operator = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->staff)->create();
    submitLeave($operator, ['leave_type_id' => $this->permit->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']);
    $leave = LeaveRequest::query()->sole();

    $this->actingAs($this->staff->user)->get('/cuti-izin?tab=approvals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('awaitingCount', 1)->has('requests.data', 1));

    decideLeave($this->staff->user, $leave, 'approve')->assertSessionHas('success');
});

test('validasi: bentrok tanggal, saldo tidak cukup (termasuk yang masih menunggu), dan tanpa hari kerja', function () {
    submitLeave($this->staff, ['start_date' => '2026-10-12', 'end_date' => '2026-10-23'])->assertSessionHasNoErrors(); // 10 hari

    submitLeave($this->staff, ['start_date' => '2026-10-16', 'end_date' => '2026-10-19', 'leave_type_id' => $this->permit->id])
        ->assertSessionHasErrors(['start_date' => 'Tanggal bentrok dengan pengajuan cuti/izin lain yang masih menunggu atau sudah disetujui.']);

    submitLeave($this->staff, ['start_date' => '2026-11-02', 'end_date' => '2026-11-04']) // 3 hari, sisa 2
        ->assertSessionHasErrors(['end_date' => 'Sisa Cuti Tahunan tidak cukup: tersedia 2 hari, diajukan 3 hari kerja.']);

    submitLeave($this->staff, ['start_date' => '2026-10-10', 'end_date' => '2026-10-11'])
        ->assertSessionHasErrors(['end_date' => 'Rentang tanggal tidak berisi hari kerja (akhir pekan atau hari libur).']);

    submitLeave($this->staff, ['start_date' => '2026-12-30', 'end_date' => '2027-01-04'])
        ->assertSessionHasErrors('end_date');

    submitLeave($this->staff, ['start_date' => '2026-11-10', 'end_date' => '2026-11-09'])
        ->assertSessionHasErrors(['end_date' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.']);

    expect(LeaveRequest::query()->count())->toBe(1);
});

test('sakit wajib lampiran; lampiran disimpan privat dan hanya bisa dibuka pihak berwenang', function () {
    submitLeave($this->staff, ['leave_type_id' => $this->sick->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08'])
        ->assertSessionHasErrors(['attachment' => 'Lampiran wajib untuk Sakit.']);

    submitLeave($this->staff, [
        'leave_type_id' => $this->sick->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08',
        'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->sole();
    Storage::disk('local')->assertExists($leave->attachment_path);

    $this->actingAs($this->manager->user)->get("/cuti-izin/{$leave->id}/lampiran")->assertOk();
    $this->actingAs(Employee::factory()->withRole(Role::Karyawan)->create()->user)
        ->get("/cuti-izin/{$leave->id}/lampiran")->assertForbidden();
});

test('pembatalan hanya oleh pemilik dan hanya saat Menunggu; tetap tersimpan di riwayat', function () {
    submitLeave($this->staff);
    $leave = LeaveRequest::query()->sole();

    $this->actingAs($this->manager->user)->post("/cuti-izin/{$leave->id}/batal")->assertForbidden();
    $this->actingAs($this->staff->user)->post("/cuti-izin/{$leave->id}/batal")->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($leave->approvalSteps()->pluck('status')->all())->toBe(['cancelled', 'cancelled']);

    $this->post("/cuti-izin/{$leave->id}/batal")->assertForbidden();

    // Setelah dibatalkan, tanggal yang sama boleh diajukan lagi.
    submitLeave($this->staff)->assertSessionHasNoErrors();
});

test('karyawan lain tidak bisa melihat pengajuan dengan mengganti ID; atasan bisa melihat milik timnya', function () {
    submitLeave($this->staff);
    $leave = LeaveRequest::query()->sole();

    $this->actingAs(Employee::factory()->withRole(Role::Karyawan)->create()->user)->get("/cuti-izin/{$leave->id}")->assertForbidden();
    $this->actingAs(Employee::factory()->withRole(Role::Manager)->create()->user)->get("/cuti-izin/{$leave->id}")->assertForbidden();

    $this->actingAs($this->manager->user)->get("/cuti-izin/{$leave->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.decide', true)->where('can.cancel', false)->has('steps', 2));

    $this->actingAs($this->hrd)->get("/cuti-izin/{$leave->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.decide', false));
});

test('halaman Cuti/Izin menampilkan saldo sendiri dan tab sesuai peran', function () {
    submitLeave($this->staff, ['start_date' => '2026-10-12', 'end_date' => '2026-10-13']);

    $this->actingAs($this->staff->user)->get('/cuti-izin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tabs', ['mine'])
            ->where('balances.0.quota', 12)
            ->where('balances.0.pending', 2)
            ->where('balances.0.remaining', 10));

    $this->actingAs($this->hrd)->get('/cuti-izin?tab=all')
        ->assertInertia(fn (Assert $page) => $page->where('tabs', ['mine', 'approvals', 'all'])->has('requests.data', 1));
});

test('karyawan yang cuti disetujui: kios menolak scan dan penandaan malam mencatat Cuti/Izin', function () {
    submitLeave($this->manager, ['leave_type_id' => $this->permit->id, 'start_date' => '2026-10-07', 'end_date' => '2026-10-07']);
    decideLeave($this->hrd, LeaveRequest::query()->sole(), 'approve');

    $this->actingAs($this->hrd)->postJson('/absensi/scan', ['token' => $this->manager->qr_token])
        ->assertStatus(422)->assertJson(['message' => 'Anda tercatat cuti/izin hari ini.']);

    $counts = app(AttendanceService::class)->markAbsentees('2026-10-07');

    expect($counts['leave'])->toBe(1)
        ->and(AttendanceRecord::query()->where('employee_id', $this->manager->id)->sole()->status)->toBe(AttendanceStatus::Leave);
});
