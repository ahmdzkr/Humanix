<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\NotificationDelivery;
use App\Models\OnboardingRecord;
use App\Models\Position;
use App\Models\User;
use App\Notifications\HrisNotification;
use App\Services\OnboardingService;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Asia/Jakarta'));
    $this->seed(SystemSettingsSeeder::class);

    $this->hrd = Employee::factory()->withRole(Role::Hrd)->create()->user;
    $this->manager = Employee::factory()->withRole(Role::Manager)->create()->user;
    $this->department = Department::factory()->create(['name' => 'Teknologi Informasi']);
    $this->position = Position::factory()->create(['department_id' => $this->department->id, 'name' => 'Programmer']);
});

test('alur lengkap: lowongan → lamaran publik → seleksi → wawancara → nilai → diterima → menjadi karyawan', function () {
    // 1. HRD membuat & mempublikasikan lowongan.
    $vacancy = createVacancy();
    expect($vacancy->status)->toBe('draft')->and($vacancy->slug)->toBe('programmer-laravel');
    $this->get('/karier')->assertInertia(fn (Assert $page) => $page->component('careers/index')->has('vacancies', 0));

    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published'])->assertSessionHas('success');

    // 2. Pelamar melamar lewat halaman publik tanpa login.
    auth()->logout();
    $this->get('/karier')->assertOk()->assertInertia(fn (Assert $page) => $page->has('vacancies', 1)->where('vacancies.0.slug', 'programmer-laravel'));
    $this->get('/karier/programmer-laravel')->assertOk();
    applyTo($vacancy)->assertRedirect('/karier/programmer-laravel/terkirim');

    $application = JobApplication::query()->sole();
    expect($application->status)->toBe(ApplicationStatus::Applied)
        ->and($application->applicant->email)->toBe('rizky@contoh.com')
        ->and($application->consent_at)->not->toBeNull();
    Storage::disk('local')->assertExists($application->cv_path);
    expect(str_starts_with($application->cv_path, 'cv/'))->toBeTrue();

    // 3. Seleksi administrasi.
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass'])->assertSessionHas('success');

    // 4. Jadwalkan wawancara dengan Manager sebagai interviewer.
    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'online',
        'location' => 'https://meet.example.com/abc', 'interviewer_id' => $this->manager->id,
    ])->assertSessionHas('success');
    $interview = Interview::query()->sole();
    expect($application->fresh()->status)->toBe(ApplicationStatus::Interview)
        ->and($interview->scheduled_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-14 03:00');

    // Interviewer diberi tahu (in-app) dan pelamar diundang lewat email; status kirim tercatat.
    Notification::assertSentTo($this->manager, HrisNotification::class,
        fn (HrisNotification $n) => $n->title === 'Jadwal wawancara baru' && str_contains($n->body, '10:00 WIB'));
    expect(NotificationDelivery::query()->where('email', $application->applicant->email)->pluck('subject')->all())
        ->toBe(['Lolos seleksi berkas: Programmer Laravel', 'Undangan wawancara: Programmer Laravel'])
        ->and(NotificationDelivery::query()->where('email', $application->applicant->email)->pluck('status')->unique()->all())->toBe(['sent']);

    // 5. Manager menilai.
    $scores = array_fill_keys(['Kompetensi teknis', 'Komunikasi', 'Pemecahan masalah', 'Kerja sama tim', 'Kesesuaian budaya kerja'], 4);
    $this->actingAs($this->manager)->post("/rekruitmen/wawancara/{$interview->id}/nilai", [
        'scores' => $scores, 'recommendation' => 'hire', 'summary' => 'Kuat di backend.',
    ])->assertSessionHas('success');
    expect($interview->fresh())->status->toBe('completed')->and($interview->fresh()->averageScore())->toBe(4.0);

    // 6. Keputusan akhir: terima.
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'accept'])->assertSessionHas('success');
    expect(NotificationDelivery::query()->where('subject', 'Selamat, Anda diterima: Programmer Laravel')->exists())->toBeTrue();

    // 7. Onboarding → karyawan + akun dalam satu langkah.
    $this->post("/rekruitmen/lamaran/{$application->id}/onboarding", [
        'nik' => 'emp-9001', 'full_name' => 'Rizky Pratama', 'email' => 'rizky@contoh.com', 'phone' => '0812-1111-2222',
        'department_id' => $this->department->id, 'position_id' => $this->position->id,
        'employment_status' => 'contract', 'join_date' => '2026-11-02', 'contract_end_date' => '2027-11-01',
    ])->assertRedirect();

    $employee = Employee::query()->where('nik', 'EMP-9001')->sole();
    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Hired)
        ->and($application->employee_id)->toBe($employee->id)
        ->and($employee->user->primaryRole())->toBe(Role::Karyawan)
        ->and(OnboardingRecord::query()->sole()->employee_id)->toBe($employee->id)
        ->and($application->histories()->pluck('to_status')->all())->toBe(['hired', 'accepted', 'interview', 'shortlisted', 'applied'])
        // Akun memakai kata sandi awal (ter-hash) yang wajib diganti; info login dikirim lewat email, bukan tautan reset.
        ->and(Hash::check(OnboardingService::DEFAULT_PASSWORD, $employee->user->password))->toBeTrue()
        ->and($employee->user->must_change_password)->toBeTrue()
        ->and(NotificationDelivery::query()->where('email', 'rizky@contoh.com')->where('category', 'account')->sole()->status)->toBe('sent');
    Notification::assertNotSentTo($employee->user, ResetPassword::class);
});

test('pelamar tidak bisa dikonversi dua kali menjadi karyawan', function () {
    $application = acceptedApplication();
    $payload = onboardPayload();

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/onboarding", $payload)->assertRedirect();
    $this->post("/rekruitmen/lamaran/{$application->id}/onboarding", [...$payload, 'nik' => 'EMP-9002', 'email' => 'lain@contoh.com'])
        ->assertSessionHasErrors(['status' => 'Pelamar ini sudah dijadikan karyawan.']);

    expect(Employee::query()->whereIn('nik', ['EMP-9001', 'EMP-9002'])->count())->toBe(1);
});

test('onboarding yang gagal validasi tidak membuat akun atau mengirim email', function () {
    $application = acceptedApplication();
    $existing = Employee::factory()->create(['nik' => 'EMP-9001']);
    $usersBefore = User::query()->count();

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/onboarding", onboardPayload())
        ->assertSessionHasErrors(['nik' => 'NIK sudah digunakan.']);

    expect(User::query()->count())->toBe($usersBefore)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Accepted);
    Notification::assertSentTimes(ResetPassword::class, 0);
});

test('onboarding yang gagal di tengah transaksi membatalkan akun yang sudah dibuat', function () {
    $application = acceptedApplication();
    $usersBefore = User::query()->count();

    // join_date kosong lolos ke service tetapi ditolak database (NOT NULL) setelah akun user dibuat.
    $payload = onboardPayload();
    unset($payload['join_date']);

    expect(fn () => app(OnboardingService::class)->onboard($application, $payload, test()->hrd))
        ->toThrow(QueryException::class);

    expect(User::query()->count())->toBe($usersBefore)
        ->and(User::query()->where('email', 'rizky@contoh.com')->exists())->toBeFalse()
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Accepted)
        ->and($application->fresh()->employee_id)->toBeNull();
    Notification::assertSentTimes(ResetPassword::class, 0);
});

test('lamaran publik: validasi CV, persetujuan data, duplikat, dan lowongan tertutup', function () {
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);

    applyTo($vacancy, ['cv' => UploadedFile::fake()->create('cv.docx', 100, 'application/msword'), 'consent' => null])
        ->assertSessionHasErrors([
            'cv' => 'CV harus berupa file PDF.',
            'consent' => 'Anda perlu menyetujui pemrosesan data pribadi untuk melamar.',
        ]);
    applyTo($vacancy, ['cv' => UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf')])
        ->assertSessionHasErrors(['cv' => 'Ukuran CV maksimal 5 MB.']);

    applyTo($vacancy)->assertRedirect();
    applyTo($vacancy, ['email' => 'rizky@CONTOH.com'])
        ->assertSessionHasErrors(['email' => 'Anda sudah melamar lowongan ini dengan email tersebut.']);
    expect(JobApplication::query()->count())->toBe(1)->and(Applicant::query()->count())->toBe(1);
    expect(Storage::disk('local')->allFiles('cv'))->toHaveCount(1);

    // Tenggat lewat → tidak tampil di daftar, tautan lama menampilkan "ditutup", dan tidak bisa dilamar.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00', 'Asia/Jakarta'));
    $this->get('/karier')->assertInertia(fn (Assert $page) => $page->has('vacancies', 0));
    $this->get('/karier/programmer-laravel')->assertOk()->assertInertia(fn (Assert $page) => $page->where('vacancy.closed', true));
    applyTo($vacancy, ['email' => 'baru@contoh.com'])->assertSessionHasErrors(['vacancy' => 'Lowongan ini sudah ditutup.']);
});

test('honeypot terisi diabaikan tanpa menyimpan lamaran', function () {
    $vacancy = createVacancy();
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);

    applyTo($vacancy, ['website' => 'http://spam.example'])->assertRedirect("/karier/{$vacancy->slug}/terkirim");

    expect(JobApplication::query()->count())->toBe(0);
});

test('skor wawancara di luar skala 1–5 ditolak dan hanya interviewer yang ditugaskan yang bisa menilai', function () {
    [$application, $interview] = interviewedApplication(scored: false);
    $scores = array_fill_keys(['Kompetensi teknis', 'Komunikasi', 'Pemecahan masalah', 'Kerja sama tim', 'Kesesuaian budaya kerja'], 3);

    $this->actingAs($this->manager)->post("/rekruitmen/wawancara/{$interview->id}/nilai", [
        'scores' => [...$scores, 'Komunikasi' => 6, 'Kerja sama tim' => 0], 'recommendation' => 'consider',
    ])->assertSessionHasErrors(['scores.1' => 'Skor "Komunikasi" harus angka 1–5.', 'scores.3']);

    $this->post("/rekruitmen/wawancara/{$interview->id}/nilai", [
        'scores' => array_diff_key($scores, ['Komunikasi' => 1]), 'recommendation' => 'consider',
    ])->assertSessionHasErrors('scores.1');

    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;
    $this->actingAs($otherManager)->post("/rekruitmen/wawancara/{$interview->id}/nilai", [
        'scores' => $scores, 'recommendation' => 'hire',
    ])->assertForbidden();
    $this->actingAs($this->hrd)->post("/rekruitmen/wawancara/{$interview->id}/nilai", [
        'scores' => $scores, 'recommendation' => 'hire',
    ])->assertForbidden();

    expect($interview->fresh()->status)->toBe('scheduled');
});

test('Manager hanya melihat kandidat yang wawancaranya ditugaskan kepadanya', function () {
    [$application] = interviewedApplication(scored: false);
    $otherManager = Employee::factory()->withRole(Role::Manager)->create()->user;

    $this->actingAs($this->manager)->get("/rekruitmen/lamaran/{$application->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false)->has('interviews', 1)->where('histories', []));
    $this->actingAs($this->manager)->get("/rekruitmen/lamaran/{$application->id}/cv")->assertOk();
    $this->actingAs($this->manager)->get('/rekruitmen')->assertInertia(fn (Assert $page) => $page->has('myInterviews', 1)->where('vacancies', []));

    $this->actingAs($otherManager)->get("/rekruitmen/lamaran/{$application->id}")->assertForbidden();
    $this->actingAs($otherManager)->get("/rekruitmen/lamaran/{$application->id}/cv")->assertForbidden();

    // Manager tidak bisa melakukan aksi HRD.
    $this->actingAs($this->manager)->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'accept'])->assertForbidden();
    $this->actingAs($this->manager)->get("/rekruitmen/lowongan/{$application->job_vacancy_id}")->assertForbidden();

    // Karyawan biasa tidak punya akses rekrutmen sama sekali; CV tidak bisa diakses publik.
    $this->actingAs(Employee::factory()->withRole(Role::Karyawan)->create()->user)->get("/rekruitmen/lamaran/{$application->id}/cv")->assertForbidden();
    auth()->logout();
    $this->get("/rekruitmen/lamaran/{$application->id}/cv")->assertRedirect(route('login'));
});

test('urutan tahap dijaga: tidak bisa wawancara sebelum lolos administrasi, keputusan butuh penilaian, kuota dibatasi', function () {
    $vacancy = createVacancy(['quota' => 1]);
    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published']);
    applyTo($vacancy);
    $application = JobApplication::query()->sole();

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1', 'interviewer_id' => $this->manager->id,
    ])->assertSessionHasErrors('status');

    $this->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'reject'])
        ->assertSessionHasErrors(['note' => 'Catatan wajib diisi saat menolak.']);

    $this->post("/rekruitmen/lamaran/{$application->id}/seleksi", ['decision' => 'pass']);
    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-11T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1', 'interviewer_id' => $this->manager->id,
    ])->assertSessionHasErrors(['scheduled_at' => 'Jadwal wawancara harus di masa depan.']);
    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1',
        'interviewer_id' => Employee::factory()->withRole(Role::Karyawan)->create()->user_id,
    ])->assertSessionHasErrors(['interviewer_id' => 'Interviewer harus pengguna aktif dengan role HRD atau Manager.']);
    $this->post("/rekruitmen/lamaran/{$application->id}/wawancara", [
        'scheduled_at' => '2026-10-14T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1', 'interviewer_id' => $this->manager->id,
    ])->assertSessionHas('success');

    $this->post("/rekruitmen/lamaran/{$application->id}/keputusan", ['decision' => 'accept'])
        ->assertSessionHasErrors(['status' => 'Belum ada wawancara yang sudah dinilai.']);

    // Kuota: pelamar kedua (sudah melamar sebelum kuota terisi) tidak bisa diterima setelah kuota 1 terisi.
    $first = $application;
    scoreInterview(Interview::query()->sole(), $this->manager);

    applyTo($vacancy, ['email' => 'kedua@contoh.com', 'full_name' => 'Kedua']);
    $second = JobApplication::query()->latest('id')->first();
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$second->id}/seleksi", ['decision' => 'pass']);
    $this->post("/rekruitmen/lamaran/{$second->id}/wawancara", [
        'scheduled_at' => '2026-10-15T10:00', 'duration_minutes' => 60, 'mode' => 'offline', 'location' => 'Ruang 1', 'interviewer_id' => $this->manager->id,
    ]);
    scoreInterview(Interview::query()->latest('id')->first(), $this->manager);

    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$first->id}/keputusan", ['decision' => 'accept'])->assertSessionHas('success');
    $this->actingAs($this->hrd)->post("/rekruitmen/lamaran/{$second->id}/keputusan", ['decision' => 'accept'])
        ->assertSessionHasErrors(['status' => 'Kuota lowongan (1 orang) sudah terpenuhi.']);
});

test('halaman rekrutmen menampilkan lowongan untuk HRD dan publikasi menolak tenggat lewat', function () {
    $vacancy = createVacancy(['deadline' => '2026-10-01']);

    $this->actingAs($this->hrd)->post("/rekruitmen/lowongan/{$vacancy->id}/status", ['status' => 'published'])
        ->assertSessionHas('error', 'Tenggat lowongan sudah lewat. Ubah tenggat sebelum mempublikasikan.');

    $this->get('/rekruitmen')->assertInertia(fn (Assert $page) => $page->component('recruitment/index')->has('vacancies', 1)->where('canManage', true));
    $this->get("/rekruitmen/lowongan/{$vacancy->id}")->assertInertia(fn (Assert $page) => $page->component('recruitment/vacancy'));
});
