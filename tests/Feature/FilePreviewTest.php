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
use App\Models\PayrollRecord;
use App\Models\Position;
use App\Models\SalaryStructure;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\OrganizationDemoSeeder;
use Database\Seeders\RecruitmentDemoSeeder;
use Database\Seeders\SalaryComponentsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Pratinjau file: PDF & file unggahan tampil inline (dibuka di modal), `?download=1` memaksa unduhan,
| ekspor Excel memberi tabel JSON lewat `?preview=1`. Aturan akses sama untuk pratinjau dan unduhan.
*/
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Jakarta'));
    $this->seed([SystemSettingsSeeder::class, SalaryComponentsSeeder::class, LeaveTypesSeeder::class]);

    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $this->manager = Employee::factory()->withRole(Role::Manager)->create();
    $this->staff = Employee::factory()->withRole(Role::Karyawan)->reportsTo($this->manager)->create(['join_date' => '2025-01-01']);
    $this->outsider = Employee::factory()->withRole(Role::Karyawan)->create();
});

function expectInline($response, string $filename): void
{
    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toStartWith('inline')->toContain($filename);
}

function expectAttachment($response, string $filename): void
{
    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toStartWith('attachment')->toContain($filename);
}

test('ID Card tampil inline untuk pratinjau dan baru diunduh dengan ?download=1', function () {
    $nik = $this->staff->nik;

    $preview = $this->actingAs($this->hrd)->get("/karyawan/{$this->staff->id}/id-card");
    $preview->assertOk()->assertHeader('content-type', 'application/pdf');
    expectInline($preview, "id-card-{$nik}.pdf");

    expectAttachment($this->actingAs($this->hrd)->get("/karyawan/{$this->staff->id}/id-card?download=1")->assertOk(), "id-card-{$nik}.pdf");

    expectInline($this->actingAs($this->staff->user)->get('/profil-saya/id-card')->assertOk(), "id-card-{$nik}.pdf");

    // Pratinjau maupun unduhan tetap ditolak untuk karyawan lain.
    $this->actingAs($this->outsider->user)->get("/karyawan/{$this->staff->id}/id-card")->assertForbidden();
    $this->actingAs($this->outsider->user)->get("/karyawan/{$this->staff->id}/id-card?download=1")->assertForbidden();
});

test('ekspor karyawan: ?preview=1 memberi tabel JSON dari data yang sama, tanpa itu tetap .xlsx', function () {
    Employee::factory()->create(['full_name' => 'Andi Pratinjau']);

    $preview = $this->actingAs($this->hrd)->getJson('/karyawan/ekspor?preview=1');
    $preview->assertOk()
        ->assertJsonStructure(['title', 'filename', 'headings', 'rows', 'total', 'truncated'])
        ->assertJsonPath('truncated', false);
    expect($preview->json('filename'))->toEndWith('.xlsx')
        ->and($preview->json('headings'))->not->toBeEmpty()
        ->and($preview->json('total'))->toBe(Employee::count())
        ->and(json_encode($preview->json('rows')))->toContain('Andi Pratinjau');

    $download = $this->actingAs($this->hrd)->get('/karyawan/ekspor');
    $download->assertOk();
    expect($download->headers->get('content-disposition'))->toContain('.xlsx');

    // Karyawan biasa tidak mendapat pratinjau dan tidak ada data yang bocor.
    $denied = $this->actingAs($this->outsider->user)->getJson('/karyawan/ekspor?preview=1');
    $denied->assertForbidden();
    expect($denied->getContent())->not->toContain('Andi Pratinjau');
});

test('rekap absensi dan rekap cuti-lembur bisa dipratinjau; akses tetap dibatasi', function () {
    $this->actingAs($this->hrd)->getJson('/absensi/ekspor?mode=monthly&month=2026-10&preview=1')
        ->assertOk()
        ->assertJsonPath('filename', 'absensi-2026-10.xlsx')
        ->assertJsonStructure(['headings', 'rows', 'total']);

    $this->actingAs($this->hrd)->getJson('/laporan/cuti-lembur?month=2026-10&preview=1')
        ->assertOk()
        ->assertJsonPath('filename', 'rekap-cuti-lembur-2026-10.xlsx')
        ->assertJsonStructure(['headings', 'rows', 'total']);

    $this->actingAs($this->staff->user)->getJson('/absensi/ekspor?mode=monthly&month=2026-10&preview=1')->assertForbidden();
});

test('slip gaji, ekspor payroll, dan bukti pembayaran: pratinjau inline/JSON, unduhan eksplisit, akses dibatasi', function () {
    SalaryStructure::create(['employee_id' => $this->staff->id, 'base_salary' => '9000000', 'effective_from' => '2026-01-01']);
    $payroll = app(PayrollService::class);
    $period = $payroll->createPeriod('2026-10', '2026-10-31')->refresh();
    $payroll->calculate($period, $this->hrd, $period->version);
    $period->refresh();
    $payroll->advance($period, PayrollStatus::Reviewed, $this->hrd, $period->version);
    $payroll->advance($period->refresh(), PayrollStatus::Approved, $this->hrd, $period->version);
    $record = PayrollRecord::query()->where('employee_id', $this->staff->id)->sole();
    $payroll->createPayments($period->refresh(), $this->hrd);
    $payment = $record->payments()->sole();
    Storage::disk('local')->put('payment-proofs/bukti.png', 'BUKTI-GAMBAR');
    $payment->forceFill(['proof_path' => 'payment-proofs/bukti.png'])->save();

    $slip = $this->actingAs($this->staff->user)->get("/gaji/slip/{$record->id}/pdf");
    $slip->assertOk()->assertHeader('content-type', 'application/pdf');
    expectInline($slip, "slip-gaji-{$period->code}-{$record->nik}.pdf");
    expectAttachment($this->actingAs($this->staff->user)->get("/gaji/slip/{$record->id}/pdf?download=1"), "slip-gaji-{$period->code}-{$record->nik}.pdf");

    $this->actingAs($this->hrd)->getJson("/gaji/periode/{$period->id}/ekspor?preview=1")
        ->assertOk()
        ->assertJsonPath('filename', "payroll-{$period->code}.xlsx")
        ->assertJsonPath('total', PayrollRecord::query()->where('payroll_period_id', $period->id)->count());

    // Jenis bukti dikirim ke halaman agar gambar dipratinjau sebagai gambar.
    $this->actingAs($this->hrd)->get("/gaji/slip/{$record->id}")
        ->assertInertia(fn (Assert $page) => $page->where('payments.0.proof_kind', 'image'));

    $proof = $this->actingAs($this->hrd)->get("/gaji/pembayaran/{$payment->id}/bukti");
    $proof->assertOk();
    expect((string) $proof->headers->get('content-disposition'))->toStartWith('inline');
    expect((string) $this->actingAs($this->hrd)->get("/gaji/pembayaran/{$payment->id}/bukti?download=1")->headers->get('content-disposition'))
        ->toStartWith('attachment');

    foreach ([$this->outsider->user, $this->manager->user] as $user) {
        $this->actingAs($user)->get("/gaji/slip/{$record->id}/pdf?download=1")->assertForbidden();
        $this->actingAs($user)->getJson("/gaji/periode/{$period->id}/ekspor?preview=1")->assertForbidden();
        $denied = $this->actingAs($user)->get("/gaji/pembayaran/{$payment->id}/bukti?download=1");
        expect($denied->status())->toBeIn([403, 404])
            ->and($denied->getContent())->not->toContain('BUKTI-GAMBAR');
    }
});

test('CV pelamar demo adalah PDF valid sehingga pratinjau inline bisa menampilkannya', function () {
    $this->seed([OrganizationDemoSeeder::class, RecruitmentDemoSeeder::class]);
    $application = JobApplication::query()->with('applicant')->whereHas('applicant', fn ($q) => $q->where('email', 'rizky.demo@contoh.com'))->sole();

    // PDF placeholder lama (tanpa halaman) membuat penampil PDF browser menampilkan "file rusak".
    $content = Storage::disk('local')->get($application->cv_path);
    expect($content)->toStartWith('%PDF-')
        ->and(preg_match('#/Type\s*/Page[^s]#', $content))->toBe(1)
        ->and($content)->toContain('%%EOF');

    $response = $this->actingAs($this->hrd)->get("/rekruitmen/lamaran/{$application->id}/cv");
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expectInline($response, 'CV-rizky-pratama.pdf');
    expect($response->streamedContent())->toBe($content);
});

test('lampiran cuti dan CV pelamar tampil inline, unduhan lewat ?download=1, pihak lain ditolak', function () {
    Storage::disk('local')->put('leave-attachments/surat.pdf', 'ISI-LAMPIRAN');
    $leave = LeaveRequest::create([
        'employee_id' => $this->staff->id, 'leave_type_id' => LeaveType::query()->where('code', 'SKT')->sole()->id,
        'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'days' => 1, 'reason' => 'Sakit',
        'attachment_path' => 'leave-attachments/surat.pdf', 'status' => 'pending',
    ]);

    $this->actingAs($this->manager->user)->get("/cuti-izin/{$leave->id}")
        ->assertInertia(fn (Assert $page) => $page->where('leave.attachment_kind', 'pdf'));
    expect((string) $this->actingAs($this->manager->user)->get("/cuti-izin/{$leave->id}/lampiran")->assertOk()->headers->get('content-disposition'))
        ->toStartWith('inline');
    expect((string) $this->actingAs($this->manager->user)->get("/cuti-izin/{$leave->id}/lampiran?download=1")->headers->get('content-disposition'))
        ->toStartWith('attachment');

    $denied = $this->actingAs($this->outsider->user)->get("/cuti-izin/{$leave->id}/lampiran?download=1");
    $denied->assertForbidden();
    expect($denied->getContent())->not->toContain('ISI-LAMPIRAN');

    Storage::disk('local')->put('cv/rizky.pdf', 'CV-PELAMAR');
    $department = Department::factory()->create();
    $vacancy = JobVacancy::create(['slug' => 'programmer', 'title' => 'Programmer', 'department_id' => $department->id,
        'position_id' => Position::factory()->create(['department_id' => $department->id])->id, 'employment_status' => 'contract',
        'location' => 'Bandung', 'description' => 'x', 'requirements' => 'x', 'quota' => 1, 'deadline' => '2026-12-31', 'status' => 'published']);
    $applicant = Applicant::create(['full_name' => 'Rizky Pelamar', 'email' => 'rizky@contoh.com', 'phone' => '0812', 'last_education' => 'S1']);
    $application = JobApplication::create(['job_vacancy_id' => $vacancy->id, 'applicant_id' => $applicant->id, 'cv_path' => 'cv/rizky.pdf',
        'consent_at' => now(), 'status' => 'applied']);

    $cv = $this->actingAs($this->hrd)->get("/rekruitmen/lamaran/{$application->id}/cv");
    $cv->assertOk();
    expectInline($cv, 'CV-rizky-pelamar.pdf');
    expectAttachment($this->actingAs($this->hrd)->get("/rekruitmen/lamaran/{$application->id}/cv?download=1"), 'CV-rizky-pelamar.pdf');

    foreach ([$this->manager->user, $this->staff->user] as $user) {
        $denied = $this->actingAs($user)->get("/rekruitmen/lamaran/{$application->id}/cv?download=1");
        expect($denied->status())->toBeIn([403, 404])
            ->and($denied->getContent())->not->toContain('CV-PELAMAR');
    }
});
