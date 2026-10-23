<?php

use App\Enums\PayrollStatus;
use App\Enums\Role;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PayrollRecord;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\SalaryStructure;
use App\Services\ApprovalService;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\SalaryComponentsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Support\Facades\Storage;

/*
| Akses horizontal: pengguna yang sah mencoba membuka data orang lain dengan mengganti ID di URL.
|
| Struktur:
|   managerA → victim (data yang diincar), teammate
|   managerB → outsider
|   colleague: Karyawan lain tanpa hubungan
| Admin & HRD dipisah. Setiap percobaan harus ditolak dan tidak membocorkan isi.
*/
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Jakarta'));
    $this->seed([SystemSettingsSeeder::class, SalaryComponentsSeeder::class, LeaveTypesSeeder::class]);

    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $this->admin = Employee::factory()->withRole(Role::Administrator)->create()->user;
    $this->managerA = Employee::factory()->withRole(Role::Manager)->create();
    $this->managerB = Employee::factory()->withRole(Role::Manager)->create();
    $this->victim = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->managerA)->create(['join_date' => '2025-01-01']);
    $this->teammate = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->managerA)->create();
    $this->outsider = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->managerB)->create();
    $this->colleague = Employee::factory()->withRole(Role::Karyawan)->create();
});

/** Percobaan akses yang harus ditolak: 403 (atau 404 untuk sumber yang disembunyikan). */
function assertDenied($response): void
{
    expect($response->status())->toBeIn([403, 404]);
}

test('slip gaji, PDF slip, dan bukti pembayaran orang lain tidak bisa dibuka', function () {
    SalaryStructure::create(['employee_id' => $this->victim->id, 'base_salary' => '9000000', 'effective_from' => '2026-01-01']);
    $payroll = app(PayrollService::class);
    $period = $payroll->createPeriod('2026-10', '2026-10-31')->refresh();
    $payroll->calculate($period, $this->hrd, $period->version);
    $period->refresh();
    $payroll->advance($period, PayrollStatus::Reviewed, $this->hrd, $period->version);
    $payroll->advance($period->refresh(), PayrollStatus::Approved, $this->hrd, $period->version);
    $record = PayrollRecord::query()->where('employee_id', $this->victim->id)->sole();
    $payroll->createPayments($period->refresh(), $this->hrd);
    $payment = $record->payments()->sole();
    Storage::disk('local')->put('payment-proofs/bukti.pdf', 'BUKTI-RAHASIA');
    $payment->forceFill(['proof_path' => 'payment-proofs/bukti.pdf'])->save();

    // Pemilik bisa.
    $this->actingAs($this->victim->user)->get("/gaji/slip/{$record->id}")->assertOk();

    // Rekan, atasan langsung, atasan lain, dan Administrator tidak bisa.
    foreach ([$this->colleague->user, $this->teammate->user, $this->managerA->user, $this->managerB->user, $this->admin] as $user) {
        assertDenied($this->actingAs($user)->get("/gaji/slip/{$record->id}"));
        assertDenied($this->actingAs($user)->get("/gaji/slip/{$record->id}/pdf"));
        assertDenied($this->actingAs($user)->get("/gaji/periode/{$period->id}"));
        $proof = $this->actingAs($user)->get("/gaji/pembayaran/{$payment->id}/bukti");
        assertDenied($proof);
        expect($proof->getContent())->not->toContain('BUKTI-RAHASIA');
    }
});

test('pengajuan cuti, lampirannya, dan pembatalan milik orang lain ditolak', function () {
    Storage::disk('local')->put('leave-attachments/surat.pdf', 'ISI-RAHASIA');
    $leave = LeaveRequest::create([
        'employee_id' => $this->victim->id, 'leave_type_id' => LeaveType::query()->where('code', 'SKT')->sole()->id,
        'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'days' => 1, 'reason' => 'Sakit',
        'attachment_path' => 'leave-attachments/surat.pdf', 'status' => 'pending',
    ]);

    $this->actingAs($this->managerA->user)->get("/cuti-izin/{$leave->id}")->assertOk(); // atasan langsung boleh

    foreach ([$this->colleague->user, $this->teammate->user, $this->managerB->user, $this->admin] as $user) {
        assertDenied($this->actingAs($user)->get("/cuti-izin/{$leave->id}"));
        $attachment = $this->actingAs($user)->get("/cuti-izin/{$leave->id}/lampiran");
        assertDenied($attachment);
        expect($attachment->getContent())->not->toContain('ISI-RAHASIA');
        assertDenied($this->actingAs($user)->post("/cuti-izin/{$leave->id}/batal"));
    }

    expect($leave->fresh()->status->value)->toBe('pending');
});

test('pengajuan lembur orang lain tidak bisa dibuka atau dibatalkan', function () {
    $overtime = OvertimeRequest::create([
        'employee_id' => $this->victim->id, 'work_date' => '2026-10-30',
        'start_at' => CarbonImmutable::parse('2026-10-30 17:00', 'Asia/Jakarta'), 'end_at' => CarbonImmutable::parse('2026-10-30 19:00', 'Asia/Jakarta'),
        'duration_minutes' => 120, 'reason' => 'Rilis', 'status' => 'pending',
    ]);

    foreach ([$this->colleague->user, $this->managerB->user, $this->admin] as $user) {
        assertDenied($this->actingAs($user)->get("/lembur/{$overtime->id}"));
        assertDenied($this->actingAs($user)->post("/lembur/{$overtime->id}/batal"));
    }

    expect($overtime->fresh()->status->value)->toBe('pending');
});

test('lembar penilaian kinerja orang lain tidak bisa dibuka atau diisi', function () {
    $period = PerformancePeriod::create(['name' => 'Semester 2 2026', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        'self_assessment_enabled' => true, 'status' => 'active', 'activated_at' => now()]);
    $review = PerformanceReview::create(['performance_period_id' => $period->id, 'employee_id' => $this->victim->id,
        'reviewer_id' => $this->managerA->user_id, 'status' => 'draft']);

    $this->actingAs($this->managerA->user)->get("/penilaian-kinerja/lembar/{$review->id}")->assertOk();

    foreach ([$this->colleague->user, $this->teammate->user, $this->managerB->user, $this->admin] as $user) {
        assertDenied($this->actingAs($user)->get("/penilaian-kinerja/lembar/{$review->id}"));
        assertDenied($this->actingAs($user)->post("/penilaian-kinerja/lembar/{$review->id}/evaluasi-diri", [
            'scores' => [], 'achievements' => 'x', 'version' => $review->version,
        ]));
    }

    // Atasan tim lain tidak bisa menilai.
    assertDenied($this->actingAs($this->managerB->user)->post("/penilaian-kinerja/lembar/{$review->id}/penilaian-atasan", ['scores' => [], 'version' => $review->version]));
    expect($review->fresh()->status->value)->toBe('draft');
});

test('data & CV pelamar hanya untuk HRD (dan interviewer yang ditugaskan)', function () {
    Storage::disk('local')->put('cv/rizky.pdf', 'CV-RAHASIA');
    $department = Department::factory()->create();
    $vacancy = JobVacancy::create(['slug' => 'programmer', 'title' => 'Programmer', 'department_id' => $department->id,
        'position_id' => Position::factory()->create(['department_id' => $department->id])->id, 'employment_status' => 'contract',
        'location' => 'Bandung', 'description' => 'x', 'requirements' => 'x', 'quota' => 1, 'deadline' => '2026-12-31', 'status' => 'published']);
    $applicant = Applicant::create(['full_name' => 'Rizky', 'email' => 'rizky@contoh.com', 'phone' => '0812', 'last_education' => 'S1']);
    $application = JobApplication::create(['job_vacancy_id' => $vacancy->id, 'applicant_id' => $applicant->id, 'cv_path' => 'cv/rizky.pdf',
        'consent_at' => now(), 'status' => 'applied']);

    $this->actingAs($this->hrd)->get("/rekruitmen/lamaran/{$application->id}")->assertOk();

    // Manager tanpa wawancara yang ditugaskan, Karyawan, dan Administrator ditolak.
    foreach ([$this->managerA->user, $this->colleague->user, $this->admin] as $user) {
        assertDenied($this->actingAs($user)->get("/rekruitmen/lamaran/{$application->id}"));
        $cv = $this->actingAs($user)->get("/rekruitmen/lamaran/{$application->id}/cv");
        assertDenied($cv);
        expect($cv->getContent())->not->toContain('CV-RAHASIA');
    }
});

test('profil, foto, dan ID Card karyawan lain hanya untuk HRD/Admin dan atasannya', function () {
    $this->actingAs($this->managerA->user)->get("/karyawan/{$this->victim->id}")->assertOk();

    foreach ([$this->colleague->user, $this->teammate->user, $this->managerB->user] as $user) {
        assertDenied($this->actingAs($user)->get("/karyawan/{$this->victim->id}"));
        assertDenied($this->actingAs($user)->get("/karyawan/{$this->victim->id}/foto"));
        assertDenied($this->actingAs($user)->get("/karyawan/{$this->victim->id}/id-card"));
        assertDenied($this->actingAs($user)->get("/karyawan/{$this->victim->id}/ubah"));
        assertDenied($this->actingAs($user)->post("/karyawan/{$this->victim->id}", ['full_name' => 'Diubah']));
    }

    expect($this->victim->fresh()->full_name)->not->toBe('Diubah');
});

test('aksi atas data orang lain ditolak 403 sebelum validasi (payload kosong tidak memancing pesan validasi)', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->victim->id, 'leave_type_id' => LeaveType::query()->where('code', 'CT')->sole()->id,
        'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'days' => 1, 'reason' => 'x', 'status' => 'pending',
    ]);
    app(ApprovalService::class)->createSteps($leave, $this->victim->load('manager'));
    $overtime = OvertimeRequest::create([
        'employee_id' => $this->victim->id, 'work_date' => '2026-10-30',
        'start_at' => CarbonImmutable::parse('2026-10-30 17:00', 'Asia/Jakarta'), 'end_at' => CarbonImmutable::parse('2026-10-30 19:00', 'Asia/Jakarta'),
        'duration_minutes' => 120, 'reason' => 'x', 'status' => 'pending',
    ]);
    app(ApprovalService::class)->createSteps($overtime, $this->victim);
    $period = PerformancePeriod::create(['name' => 'S2', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        'self_assessment_enabled' => true, 'status' => 'active', 'activated_at' => now()]);
    $review = PerformanceReview::create(['performance_period_id' => $period->id, 'employee_id' => $this->victim->id,
        'reviewer_id' => $this->managerA->user_id, 'status' => 'self_assessed']);

    $routes = [
        "/cuti-izin/{$leave->id}/keputusan",
        "/cuti-izin/{$leave->id}/batal",
        "/lembur/{$overtime->id}/keputusan",
        "/lembur/{$overtime->id}/batal",
        "/penilaian-kinerja/lembar/{$review->id}/evaluasi-diri",
        "/penilaian-kinerja/lembar/{$review->id}/penilaian-atasan",
        "/penilaian-kinerja/lembar/{$review->id}/rencana",
        "/penilaian-kinerja/lembar/{$review->id}/keputusan-hrd",
        "/penilaian-kinerja/lembar/{$review->id}/publikasikan",
    ];

    $statuses = [];

    foreach ([$this->colleague->user, $this->managerB->user] as $user) {
        foreach ($routes as $route) {
            $statuses["{$user->name} {$route}"] = $this->actingAs($user)->post($route, [])->status();
        }
    }

    expect(collect($statuses)->reject(fn ($s) => in_array($s, [403, 404], true))->all())->toBe([]);
});
